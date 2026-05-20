require("dotenv").config();

const crypto = require("crypto");
const express = require("express");
const fs = require("fs");
const fsp = require("fs/promises");
const os = require("os");
const path = require("path");
const { Readable } = require("stream");
const { pipeline } = require("stream/promises");
const { spawn } = require("child_process");

const app = express();

const PORT = Number(process.env.PORT || 3000);
const RENDER_API_KEY = String(process.env.RENDER_API_KEY || "");
const PUBLIC_BASE_URL = String(process.env.PUBLIC_BASE_URL || `http://localhost:${PORT}`).replace(/\/+$/, "");
const MAX_RENDER_SECONDS = Math.max(30, Number(process.env.MAX_RENDER_SECONDS || 300));
const FFMPEG_BIN = process.env.FFMPEG_PATH || "ffmpeg";
const FFPROBE_BIN = process.env.FFPROBE_PATH || "ffprobe";

const ROOT_DIR = __dirname;
const TEMP_DIR = path.join(ROOT_DIR, "temp");
const RENDERS_DIR = path.join(ROOT_DIR, "renders");

app.disable("x-powered-by");
app.use(express.json({ limit: "1mb" }));
app.use("/renders", express.static(RENDERS_DIR, {
  immutable: true,
  maxAge: "7d"
}));

function jsonError(res, status, code, message, debug = "") {
  return res.status(status).json({
    success: false,
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
    jobId: String(body.job_id || `stlai_video_${crypto.randomUUID()}`).replace(/[^a-zA-Z0-9_-]/g, "_").slice(0, 120),
    format,
    audioUrl,
    clips: normalizedClips,
    transition: String(body.transition || "fade"),
    fadeDuration: clampNumber(body.fade_duration, 0.1, 2, 0.4),
    repeatClipsUntilAudioEnds: body.repeat_clips_until_audio_ends !== false,
    trimToAudioDuration: body.trim_to_audio_duration !== false,
    removeClipAudio: body.remove_clip_audio !== false
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

function outputDimensions(format) {
  return format === "9:16"
    ? { width: 1080, height: 1920 }
    : { width: 1920, height: 1080 };
}

async function ensureDirs() {
  await fsp.mkdir(TEMP_DIR, { recursive: true });
  await fsp.mkdir(RENDERS_DIR, { recursive: true });
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
      reject(publicError("PROCESS_TIMEOUT", "Tempo limite de renderização excedido.", `${command} timeout after ${options.timeoutSeconds || MAX_RENDER_SECONDS}s`));
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

      reject(publicError("FFMPEG_ERROR", "FFmpeg não conseguiu compor o vídeo final.", `${command} exit=${code}; ${stderr}`));
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

function buildSequence(clips, audioDuration, fadeDuration) {
  const sequence = [];
  let timeline = 0;
  let cursor = 0;

  while (timeline < audioDuration) {
    const clip = clips[cursor % clips.length];
    sequence.push(clip);
    timeline += clip.duration;
    if (sequence.length > 1) {
      timeline -= fadeDuration;
    }
    cursor += 1;

    if (sequence.length > 120) {
      throw publicError("SEQUENCE_TOO_LONG", "A narração está longa demais para este serviço.", `sequence=${sequence.length}`);
    }
  }

  return sequence;
}

function buildXfadeFilter(sequence, format, fadeDuration) {
  const { width, height } = outputDimensions(format);
  const filters = sequence.map((clip, idx) => {
    const duration = formatSeconds(clip.duration);
    return `[${idx}:v]scale=${width}:${height}:force_original_aspect_ratio=increase,crop=${width}:${height},setsar=1,fps=30,format=yuv420p,trim=duration=${duration},setpts=PTS-STARTPTS[v${idx}]`;
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

function formatSeconds(value) {
  return Number(value).toFixed(3).replace(/\.?0+$/, "");
}

async function composeVideo({ audioPath, clipPaths, format, outputPath, audioDuration, fadeDuration }) {
  const clipDurations = [];
  for (const clipPath of clipPaths) {
    clipDurations.push(await probeDuration(clipPath));
  }

  const baseClips = clipPaths.map((clipPath, idx) => ({
    path: clipPath,
    duration: clipDurations[idx]
  }));

  const sequence = buildSequence(baseClips, audioDuration, fadeDuration);
  const audioIndex = sequence.length;
  const filter = buildXfadeFilter(sequence, format, fadeDuration);

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
  args.push("-preset", "veryfast");
  args.push("-crf", "20");
  args.push("-pix_fmt", "yuv420p");
  args.push("-c:a", "aac");
  args.push("-b:a", "192k");
  args.push("-movflags", "+faststart");
  args.push("-shortest");
  args.push(outputPath);

  await runProcess(FFMPEG_BIN, args, { timeoutSeconds: MAX_RENDER_SECONDS });

  const stat = await fsp.stat(outputPath);
  if (!stat.size) {
    throw publicError("OUTPUT_EMPTY", "O vídeo final foi gerado vazio.", "output size=0");
  }

  return {
    repeatedClips: sequence.length,
    clipDurations
  };
}

async function removeDirSafe(dir) {
  if (!dir || path.dirname(dir) !== TEMP_DIR) return;
  await fsp.rm(dir, { recursive: true, force: true });
}

app.get("/health", async (req, res) => {
  res.json({
    ok: true,
    ffmpeg: await ffmpegAvailable()
  });
});

app.post("/render", requireAuth, async (req, res) => {
  let workDir = "";
  try {
    await ensureDirs();

    const payload = validateRenderBody(req.body);
    const renderId = `${payload.jobId}_${crypto.randomUUID()}`;
    workDir = path.join(TEMP_DIR, renderId);
    await fsp.mkdir(workDir, { recursive: true });

    const audioPath = path.join(workDir, "audio.mp3");
    const clipPaths = payload.clips.map((clip) => path.join(workDir, `clip-${clip.index}.mp4`));

    await Promise.all([
      downloadFile(payload.audioUrl, audioPath),
      ...payload.clips.map((clip, idx) => downloadFile(clip.url, clipPaths[idx]))
    ]);

    const audioDuration = await probeDuration(audioPath);
    const outputName = `stlai-final-${renderId}.mp4`;
    const outputPath = path.join(RENDERS_DIR, outputName);

    const debug = await composeVideo({
      audioPath,
      clipPaths,
      format: payload.format,
      outputPath,
      audioDuration,
      fadeDuration: payload.fadeDuration
    });

    await removeDirSafe(workDir);

    return res.json({
      success: true,
      final_video_url: `${PUBLIC_BASE_URL}/renders/${outputName}`,
      duration: Number(audioDuration.toFixed(3)),
      message: "Vídeo final composto com sucesso.",
      debug: `clips=${debug.repeatedClips}; fade=${payload.fadeDuration}; format=${payload.format}`
    });
  } catch (err) {
    if (workDir) {
      await removeDirSafe(workDir).catch(() => {});
    }

    const status = err.publicCode === "UNAUTHORIZED" ? 401 : 400;
    return jsonError(
      res,
      status,
      err.publicCode || "COMPOSER_ERROR",
      err.publicMessage || "Não foi possível compor o vídeo final.",
      err.publicDebug || err.message
    );
  }
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
    });
  })
  .catch((err) => {
    console.error("Failed to initialize renderer:", safeDebug(err.message));
    process.exit(1);
  });
