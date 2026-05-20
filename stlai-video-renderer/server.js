require("dotenv").config();

const crypto = require("crypto");
const express = require("express");
const fs = require("fs");
const fsp = require("fs/promises");
const path = require("path");
const { Readable } = require("stream");
const { pipeline } = require("stream/promises");
const { spawn } = require("child_process");

const app = express();

const PORT = Number(process.env.PORT || 3000);
const RENDER_API_KEY = String(process.env.RENDER_API_KEY || "");
const PUBLIC_BASE_URL = String(process.env.PUBLIC_BASE_URL || `http://localhost:${PORT}`).replace(/\/+$/, "");
const MAX_RENDER_SECONDS = Math.max(30, Number(process.env.MAX_RENDER_SECONDS || 300));
const RENDER_OUTPUT_QUALITY = String(process.env.RENDER_OUTPUT_QUALITY || "preview").toLowerCase() === "full" ? "full" : "preview";
const ENABLE_XFADE = String(process.env.ENABLE_XFADE || "false").toLowerCase() === "true";
const EFFECTIVE_XFADE = ENABLE_XFADE && RENDER_OUTPUT_QUALITY === "full";
const FFMPEG_BIN = process.env.FFMPEG_PATH || "ffmpeg";
const FFPROBE_BIN = process.env.FFPROBE_PATH || "ffprobe";

const ROOT_DIR = __dirname;
const TEMP_DIR = path.join(ROOT_DIR, "temp");
const JOBS_DIR = path.join(TEMP_DIR, "jobs");
const RENDERS_DIR = path.join(ROOT_DIR, "renders");

app.disable("x-powered-by");
app.use(express.json({ limit: "1mb" }));
app.use("/renders", express.static(RENDERS_DIR, {
  immutable: true,
  maxAge: "7d"
}));

function jsonError(res, status, code, message, debug = "", extra = {}) {
  return res.status(status).json({
    success: false,
    ...extra,
    message,
    code,
    debug: safeDebug(debug)
  });
}

function safeDebug(value) {
  return String(value || "")
    .replace(/Bearer\s+[A-Za-z0-9._~+/=-]+/gi, "Bearer [redacted]")
    .replace(/(api[_-]?key|token|authorization)\s*[:=]\s*[^;\s]+/gi, "$1=[redacted]")
    .replace(/\s+/g, " ")
    .slice(0, 700);
}

function logJob(renderJobId, step, details = "") {
  console.log(`[${renderJobId}] ${step}${details ? ` - ${safeDebug(details)}` : ""}`);
}

function requireAuth(req, res, next) {
  if (!RENDER_API_KEY || RENDER_API_KEY === "troque-esta-chave") {
    return jsonError(res, 500, "RENDER_API_KEY_MISSING", "Serviço de renderização sem API key configurada.", "RENDER_API_KEY ausente ou exemplo padrão.");
  }

  const header = String(req.headers.authorization || "");
  const match = header.match(/^Bearer\s+(.+)$/i);
  if (!match || match[1] !== RENDER_API_KEY) {
    return jsonError(res, 401, "UNAUTHORIZED", "Não autorizado.", "Authorization Bearer ausente ou inválido.");
  }

  return next();
}

function validateUrl(value, field) {
  if (!value || typeof value !== "string") {
    throw publicError("INVALID_PAYLOAD", `${field} é obrigatório.`, `${field} vazio.`);
  }

  let parsed;
  try {
    parsed = new URL(value);
  } catch (err) {
    throw publicError("INVALID_PAYLOAD", `${field} inválido.`, `${field} não é URL válida.`);
  }

  if (!["https:", "http:"].includes(parsed.protocol)) {
    throw publicError("INVALID_PAYLOAD", `${field} deve ser uma URL HTTP ou HTTPS.`, `${field} protocolo inválido.`);
  }

  return parsed.toString();
}

function validateRenderBody(body) {
  if (!body || typeof body !== "object" || Array.isArray(body)) {
    throw publicError("INVALID_PAYLOAD", "Payload inválido.", "body precisa ser objeto JSON.");
  }

  const format = String(body.format || "");
  if (!["9:16", "16:9"].includes(format)) {
    throw publicError("INVALID_FORMAT", "Formato de vídeo inválido.", "format aceito: 9:16 ou 16:9.");
  }

  const audioUrl = validateUrl(body.audio_url, "audio_url");
  const clips = Array.isArray(body.clips) ? body.clips : [];
  if (clips.length !== 4) {
    throw publicError("INVALID_CLIPS", "Envie exatamente 4 clipes para composição.", `clips=${clips.length}`);
  }

  const normalizedClips = clips
    .map((clip, idx) => {
      if (!clip || typeof clip !== "object" || Array.isArray(clip)) {
        throw publicError("INVALID_CLIPS", "Cada clipe deve ser um objeto válido.", `clip ${idx + 1} inválido.`);
      }

      return {
        index: Number(clip.index || idx + 1),
        role: String(clip.role || ""),
        url: validateUrl(clip.url, `clips[${idx}].url`)
      };
    })
    .sort((a, b) => a.index - b.index);

  return {
    sourceJobId: String(body.job_id || `stlai_video_${crypto.randomUUID()}`).replace(/[^a-zA-Z0-9_-]/g, "_").slice(0, 120),
    format,
    audioUrl,
    clips: normalizedClips,
    transition: String(body.transition || "fade"),
    fadeDuration: clampNumber(body.fade_duration, 0.1, 2, 0.4),
    repeatClipsUntilAudioEnds: body.repeat_clips_until_audio_ends !== false,
    trimToAudioDuration: body.trim_to_audio_duration !== false,
    removeClipAudio: body.remove_clip_audio !== false,
    enableFade: Boolean(body.enable_fade) && EFFECTIVE_XFADE
  };
}

function clampNumber(value, min, max, fallback) {
  const parsed = Number(value);
  if (!Number.isFinite(parsed)) return fallback;
  return Math.min(max, Math.max(min, parsed));
}

function publicError(code, message, debug = "") {
  const err = new Error(message);
  err.publicCode = code;
  err.publicMessage = message;
  err.publicDebug = debug;
  return err;
}

function targetSettings(format) {
  const full = RENDER_OUTPUT_QUALITY === "full";
  if (format === "9:16") {
    return {
      width: full ? 1080 : 720,
      height: full ? 1920 : 1280,
      fps: full ? 30 : 24,
      preset: full ? "veryfast" : "ultrafast",
      crf: full ? "22" : "26"
    };
  }

  return {
    width: full ? 1920 : 1280,
    height: full ? 1080 : 720,
    fps: full ? 30 : 24,
    preset: full ? "veryfast" : "ultrafast",
    crf: full ? "22" : "26"
  };
}

async function ensureDirs() {
  await fsp.mkdir(TEMP_DIR, { recursive: true });
  await fsp.mkdir(JOBS_DIR, { recursive: true });
  await fsp.mkdir(RENDERS_DIR, { recursive: true });
}

function jobPath(renderJobId) {
  const safeId = String(renderJobId || "").replace(/[^a-zA-Z0-9_-]/g, "");
  return path.join(JOBS_DIR, `${safeId}.json`);
}

async function writeJob(renderJobId, data) {
  const now = new Date().toISOString();
  let previous = {};
  try {
    previous = JSON.parse(await fsp.readFile(jobPath(renderJobId), "utf8"));
  } catch (err) {
    previous = {};
  }

  const next = {
    ...previous,
    ...data,
    render_job_id: renderJobId,
    updated_at: now,
    created_at: previous.created_at || data.created_at || now
  };
  await fsp.writeFile(jobPath(renderJobId), JSON.stringify(next, null, 2));
  return next;
}

async function readJob(renderJobId) {
  const safeId = String(renderJobId || "").replace(/[^a-zA-Z0-9_-]/g, "");
  if (!safeId) return null;

  try {
    return JSON.parse(await fsp.readFile(jobPath(safeId), "utf8"));
  } catch (err) {
    return null;
  }
}

async function downloadFile(url, outputPath) {
  const response = await fetch(url, {
    redirect: "follow",
    headers: {
      "User-Agent": "STLAI-Video-Renderer/1.0"
    }
  });

  if (!response.ok || !response.body) {
    throw publicError("DOWNLOAD_ERROR", "Não foi possível baixar um dos arquivos de mídia.", `download ${response.status} ${url}`);
  }

  await pipeline(Readable.fromWeb(response.body), fs.createWriteStream(outputPath));
  const stat = await fsp.stat(outputPath);
  if (!stat.size) {
    throw publicError("DOWNLOAD_ERROR", "Arquivo de mídia baixado está vazio.", `empty file: ${path.basename(outputPath)}`);
  }
}

function runProcess(command, args, options = {}) {
  return new Promise((resolve, reject) => {
    const child = spawn(command, args, {
      stdio: ["ignore", "pipe", "pipe"],
      windowsHide: true
    });

    let stdout = "";
    let stderr = "";
    const timer = setTimeout(() => {
      child.kill("SIGKILL");
      reject(publicError("COMPOSER_TIMEOUT", "Tempo limite de renderização excedido.", `${command} timeout after ${options.timeoutSeconds || MAX_RENDER_SECONDS}s`));
    }, (options.timeoutSeconds || MAX_RENDER_SECONDS) * 1000);

    child.stdout.on("data", (chunk) => {
      stdout += chunk.toString();
      stdout = stdout.slice(-4000);
    });

    child.stderr.on("data", (chunk) => {
      stderr += chunk.toString();
      stderr = stderr.slice(-5000);
    });

    child.on("error", (err) => {
      clearTimeout(timer);
      reject(publicError("PROCESS_ERROR", "Não foi possível executar FFmpeg/FFprobe.", err.message));
    });

    child.on("close", (code) => {
      clearTimeout(timer);
      if (code === 0) {
        resolve({ stdout, stderr });
        return;
      }

      reject(publicError("COMPOSER_RENDER_ERROR", "Não foi possível compor o vídeo final.", `${command} exit=${code}; ${stderr}`));
    });
  });
}

async function probeDuration(filePath) {
  const result = await runProcess(FFPROBE_BIN, [
    "-v", "error",
    "-show_entries", "format=duration",
    "-of", "default=noprint_wrappers=1:nokey=1",
    filePath
  ], { timeoutSeconds: 30 });

  const duration = Number(String(result.stdout || "").trim());
  if (!Number.isFinite(duration) || duration <= 0) {
    throw publicError("PROBE_ERROR", "Não foi possível detectar a duração da mídia.", `duration inválida: ${path.basename(filePath)}`);
  }

  return duration;
}

async function ffmpegAvailable() {
  try {
    await runProcess(FFMPEG_BIN, ["-version"], { timeoutSeconds: 10 });
    await runProcess(FFPROBE_BIN, ["-version"], { timeoutSeconds: 10 });
    return true;
  } catch (err) {
    return false;
  }
}

function buildSequence(clips, audioDuration, overlap = 0) {
  const sequence = [];
  let timeline = 0;
  let cursor = 0;

  while (timeline < audioDuration) {
    const clip = clips[cursor % clips.length];
    sequence.push(clip);
    timeline += clip.duration;
    if (sequence.length > 1 && overlap > 0) {
      timeline -= overlap;
    }
    cursor += 1;

    if (sequence.length > 160) {
      throw publicError("SEQUENCE_TOO_LONG", "A narração está longa demais para este serviço.", `sequence=${sequence.length}`);
    }
  }

  return sequence;
}

function formatSeconds(value) {
  return Number(value).toFixed(3).replace(/\.?0+$/, "");
}

function normalizeFilter(settings) {
  return `scale=${settings.width}:${settings.height}:force_original_aspect_ratio=increase,crop=${settings.width}:${settings.height},setsar=1,fps=${settings.fps},format=yuv420p`;
}

async function normalizeClip(inputPath, outputPath, format) {
  const settings = targetSettings(format);
  const args = [
    "-y",
    "-i", inputPath,
    "-an",
    "-vf", normalizeFilter(settings),
    "-c:v", "libx264",
    "-preset", settings.preset,
    "-crf", settings.crf,
    "-pix_fmt", "yuv420p",
    "-movflags", "+faststart",
    outputPath
  ];

  await runProcess(FFMPEG_BIN, args, { timeoutSeconds: MAX_RENDER_SECONDS });
  const stat = await fsp.stat(outputPath);
  if (!stat.size) {
    throw publicError("NORMALIZE_ERROR", "Um clipe normalizado ficou vazio.", `empty file: ${path.basename(outputPath)}`);
  }
}

async function writeConcatList(listPath, sequence) {
  const lines = sequence
    .map((clip) => `file '${clip.path.replace(/'/g, "'\\''")}'`)
    .join("\n");
  await fsp.writeFile(listPath, `${lines}\n`);
}

async function composeConcat({ audioPath, normalizedClips, outputPath, audioDuration, workDir, format }) {
  const clipDurations = [];
  for (const clipPath of normalizedClips) {
    clipDurations.push(await probeDuration(clipPath));
  }

  const baseClips = normalizedClips.map((clipPath, idx) => ({
    path: clipPath,
    duration: clipDurations[idx]
  }));

  const sequence = buildSequence(baseClips, audioDuration, 0);
  const listPath = path.join(workDir, "concat-list.txt");
  await writeConcatList(listPath, sequence);

  const settings = targetSettings(format);
  const args = [
    "-y",
    "-f", "concat",
    "-safe", "0",
    "-i", listPath,
    "-i", audioPath,
    "-t", formatSeconds(audioDuration),
    "-map", "0:v:0",
    "-map", "1:a:0",
    "-c:v", "libx264",
    "-preset", settings.preset,
    "-crf", settings.crf,
    "-pix_fmt", "yuv420p",
    "-c:a", "aac",
    "-b:a", "160k",
    "-movflags", "+faststart",
    "-shortest",
    outputPath
  ];

  await runProcess(FFMPEG_BIN, args, { timeoutSeconds: MAX_RENDER_SECONDS });
  return { repeatedClips: sequence.length, fallbackUsed: false };
}

function buildXfadeFilter(sequence, fadeDuration) {
  const filters = sequence.map((clip, idx) => {
    const duration = formatSeconds(clip.duration);
    return `[${idx}:v]trim=duration=${duration},setpts=PTS-STARTPTS[v${idx}]`;
  });

  if (sequence.length === 1) {
    filters.push("[v0]copy[vout]");
    return filters.join(";");
  }

  let previous = "v0";
  let offset = sequence[0].duration - fadeDuration;
  for (let idx = 1; idx < sequence.length; idx += 1) {
    const output = idx === sequence.length - 1 ? "vout" : `x${idx}`;
    filters.push(`[${previous}][v${idx}]xfade=transition=fade:duration=${formatSeconds(fadeDuration)}:offset=${formatSeconds(offset)}[${output}]`);
    previous = output;
    offset += sequence[idx].duration - fadeDuration;
  }

  return filters.join(";");
}

async function composeXfade({ audioPath, normalizedClips, outputPath, audioDuration, fadeDuration, format }) {
  const clipDurations = [];
  for (const clipPath of normalizedClips) {
    clipDurations.push(await probeDuration(clipPath));
  }

  const baseClips = normalizedClips.map((clipPath, idx) => ({
    path: clipPath,
    duration: clipDurations[idx]
  }));

  const sequence = buildSequence(baseClips, audioDuration, fadeDuration);
  const audioIndex = sequence.length;
  const filter = buildXfadeFilter(sequence, fadeDuration);
  const settings = targetSettings(format);
  const args = ["-y"];

  sequence.forEach((clip) => {
    args.push("-i", clip.path);
  });
  args.push("-i", audioPath);
  args.push("-filter_complex", filter);
  args.push("-map", "[vout]");
  args.push("-map", `${audioIndex}:a:0`);
  args.push("-t", formatSeconds(audioDuration));
  args.push("-c:v", "libx264");
  args.push("-preset", settings.preset);
  args.push("-crf", settings.crf);
  args.push("-pix_fmt", "yuv420p");
  args.push("-c:a", "aac");
  args.push("-b:a", "160k");
  args.push("-movflags", "+faststart");
  args.push("-shortest");
  args.push(outputPath);

  await runProcess(FFMPEG_BIN, args, { timeoutSeconds: MAX_RENDER_SECONDS });
  return { repeatedClips: sequence.length, fallbackUsed: false };
}

async function assertOutput(outputPath) {
  const stat = await fsp.stat(outputPath);
  if (!stat.size) {
    throw publicError("OUTPUT_EMPTY", "O vídeo final foi gerado vazio.", "output size=0");
  }
}

async function removeDirSafe(dir) {
  if (!dir || path.dirname(dir) !== TEMP_DIR) return;
  await fsp.rm(dir, { recursive: true, force: true });
}

async function processRenderJob(renderJobId, payload) {
  let workDir = "";
  try {
    const settings = targetSettings(payload.format);
    logJob(renderJobId, "processing", `quality=${RENDER_OUTPUT_QUALITY}; xfade=${payload.enableFade}; ${settings.width}x${settings.height}`);
    await writeJob(renderJobId, {
      status: "processing",
      progress: 10,
      message: "Baixando arquivos de mídia...",
      quality: RENDER_OUTPUT_QUALITY,
      xfade: payload.enableFade,
      target_width: settings.width,
      target_height: settings.height
    });

    workDir = path.join(TEMP_DIR, renderJobId);
    await fsp.mkdir(workDir, { recursive: true });

    const audioPath = path.join(workDir, "audio.mp3");
    const sourceClipPaths = payload.clips.map((clip) => path.join(workDir, `source-clip-${clip.index}.mp4`));
    const normalizedClipPaths = payload.clips.map((clip) => path.join(workDir, `normalized-clip-${clip.index}.mp4`));

    await Promise.all([
      downloadFile(payload.audioUrl, audioPath),
      ...payload.clips.map((clip, idx) => downloadFile(clip.url, sourceClipPaths[idx]))
    ]);

    await writeJob(renderJobId, {
      status: "processing",
      progress: 28,
      message: "Detectando duração da narração..."
    });

    const audioDuration = await probeDuration(audioPath);
    logJob(renderJobId, "audio_duration", `${formatSeconds(audioDuration)}s`);

    await writeJob(renderJobId, {
      status: "processing",
      progress: 40,
      duration: Number(audioDuration.toFixed(3)),
      message: "Normalizando clipes para composição leve..."
    });

    for (let idx = 0; idx < sourceClipPaths.length; idx += 1) {
      await normalizeClip(sourceClipPaths[idx], normalizedClipPaths[idx], payload.format);
      await writeJob(renderJobId, {
        status: "processing",
        progress: 40 + ((idx + 1) * 8),
        message: `Normalizando clipe ${idx + 1} de 4...`
      });
    }

    const outputName = `stlai-final-${renderJobId}.mp4`;
    const outputPath = path.join(RENDERS_DIR, outputName);
    let debug = null;
    let transitionUsed = "concat";
    let fallbackUsed = "";

    await writeJob(renderJobId, {
      status: "processing",
      progress: 78,
      message: "Compondo vídeo final..."
    });

    if (payload.enableFade) {
      try {
        debug = await composeXfade({
          audioPath,
          normalizedClips: normalizedClipPaths,
          outputPath,
          audioDuration,
          fadeDuration: payload.fadeDuration,
          format: payload.format
        });
        transitionUsed = "xfade";
      } catch (err) {
        fallbackUsed = "concat_without_fade";
        logJob(renderJobId, "xfade_fallback", err.publicDebug || err.message);
        debug = await composeConcat({
          audioPath,
          normalizedClips: normalizedClipPaths,
          outputPath,
          audioDuration,
          workDir,
          format: payload.format
        });
        transitionUsed = "concat";
      }
    } else {
      debug = await composeConcat({
        audioPath,
        normalizedClips: normalizedClipPaths,
        outputPath,
        audioDuration,
        workDir,
        format: payload.format
      });
      transitionUsed = "concat";
    }

    await assertOutput(outputPath);
    await removeDirSafe(workDir);

    const finalVideoUrl = `${PUBLIC_BASE_URL}/renders/${outputName}`;
    logJob(renderJobId, "ready", `duration=${formatSeconds(audioDuration)}s; transition=${transitionUsed}; fallback=${fallbackUsed || "none"}; url=${finalVideoUrl}`);

    await writeJob(renderJobId, {
      success: true,
      status: "ready",
      progress: 100,
      final_video_url: finalVideoUrl,
      duration: Number(audioDuration.toFixed(3)),
      transition_used: transitionUsed,
      fallback_used: fallbackUsed,
      message: "Vídeo final composto com sucesso.",
      debug: `clips=${debug.repeatedClips}; quality=${RENDER_OUTPUT_QUALITY}; xfade=${payload.enableFade}; transition=${transitionUsed}; fallback=${fallbackUsed || "none"}`
    });
  } catch (err) {
    if (workDir) {
      await removeDirSafe(workDir).catch(() => {});
    }

    logJob(renderJobId, "error", err.publicDebug || err.message);
    await writeJob(renderJobId, {
      success: false,
      status: "error",
      progress: 100,
      code: err.publicCode || "COMPOSER_RENDER_ERROR",
      message: err.publicMessage || "Não foi possível compor o vídeo final.",
      debug: safeDebug(err.publicDebug || err.message)
    });
  }
}

app.get("/health", async (req, res) => {
  res.json({
    ok: true,
    ffmpeg: await ffmpegAvailable(),
    quality: RENDER_OUTPUT_QUALITY,
    xfade: EFFECTIVE_XFADE
  });
});

app.post("/render", requireAuth, async (req, res) => {
  try {
    await ensureDirs();

    const payload = validateRenderBody(req.body);
    const renderJobId = `render_${crypto.randomUUID()}`;
    const settings = targetSettings(payload.format);

    await writeJob(renderJobId, {
      success: true,
      status: "queued",
      progress: 5,
      message: "Composição recebida e iniciada.",
      source_job_id: payload.sourceJobId,
      format: payload.format,
      quality: RENDER_OUTPUT_QUALITY,
      xfade: payload.enableFade,
      target_width: settings.width,
      target_height: settings.height
    });

    setImmediate(() => {
      processRenderJob(renderJobId, payload).catch((err) => {
        logJob(renderJobId, "background_error", err.message);
      });
    });

    return res.json({
      success: true,
      render_job_id: renderJobId,
      status: "queued",
      message: "Composição recebida e iniciada."
    });
  } catch (err) {
    return jsonError(
      res,
      400,
      err.publicCode || "COMPOSER_JOB_START_ERROR",
      err.publicMessage || "Não foi possível iniciar a composição.",
      err.publicDebug || err.message
    );
  }
});

app.get("/render/:render_job_id", requireAuth, async (req, res) => {
  const renderJobId = String(req.params.render_job_id || "");
  const job = await readJob(renderJobId);

  if (!job) {
    return jsonError(
      res,
      404,
      "COMPOSER_STATUS_ERROR",
      "Job de composição não encontrado.",
      `render_job_id=${renderJobId}`,
      { render_job_id: renderJobId, status: "error" }
    );
  }

  if (job.status === "error") {
    return jsonError(
      res,
      200,
      job.code || "COMPOSER_RENDER_ERROR",
      job.message || "Não foi possível compor o vídeo final.",
      job.debug || "",
      {
        render_job_id: job.render_job_id,
        status: "error",
        progress: Number(job.progress || 100),
        transition_used: job.transition_used || "",
        fallback_used: job.fallback_used || ""
      }
    );
  }

  return res.json({
    success: true,
    render_job_id: job.render_job_id,
    status: job.status || "processing",
    progress: Number(job.progress || 0),
    final_video_url: job.final_video_url || "",
    duration: Number(job.duration || 0),
    transition_used: job.transition_used || "",
    fallback_used: job.fallback_used || "",
    message: job.message || "Composição final em andamento..."
  });
});

app.use((req, res) => {
  jsonError(res, 404, "NOT_FOUND", "Rota não encontrada.", req.path);
});

app.use((err, req, res, next) => {
  if (res.headersSent) {
    return next(err);
  }

  return jsonError(res, 400, "BAD_REQUEST", "Request inválido.", err && err.message ? err.message : "Erro inesperado.");
});

ensureDirs()
  .then(() => {
    app.listen(PORT, () => {
      console.log(`STLAI video renderer listening on port ${PORT}`);
      console.log(`Renderer quality=${RENDER_OUTPUT_QUALITY}; xfade=${EFFECTIVE_XFADE}`);
    });
  })
  .catch((err) => {
    console.error("Failed to initialize renderer:", safeDebug(err.message));
    process.exit(1);
  });
