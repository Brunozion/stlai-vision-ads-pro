const assert=require('node:assert/strict');
const fs=require('node:fs');
const os=require('node:os');
const path=require('node:path');
const {execFileSync}=require('node:child_process');
const {validateRenderBody,buildSequence,buildXfadeFilter,composeXfade,probeDuration,targetSettings}=require('../stlai-video-renderer/server');
const base={format:'1:1',narration_enabled:false,clips:[{index:1,url:'https://example.test/base.mp4'}],video_generation_mode:'reference_video'};
assert.equal(validateRenderBody(base).clips.length,1);
assert.throws(()=>validateRenderBody({...base,video_generation_mode:'clips'}));
assert.throws(()=>validateRenderBody({...base,clips:[...base.clips,...base.clips]}));
assert.equal(validateRenderBody({...base,video_generation_mode:'clips',clips:Array.from({length:4},(_,i)=>({index:i+1,url:`https://example.test/${i}.mp4`}))}).clips.length,4);
const repeated=buildSequence([{path:'base.mp4',duration:10}],26,.5);
assert.equal(repeated.length,3);
const filter=buildXfadeFilter(repeated,.5,targetSettings('1:1'));
assert(filter.includes('offset=9.5') && filter.includes('offset=19'));
const real=buildXfadeFilter([{duration:7},{duration:6},{duration:9},{duration:8}],.5);
assert(real.includes('offset=6.5') && real.includes('offset=12') && real.includes('offset=20.5'));
if(!process.argv.includes('--render')){ console.log('Renderer reference-video contracts OK'); process.exit(0); }
(async()=>{
  const dir=fs.mkdtempSync(path.join(os.tmpdir(),'stlai-reference-render-'));
  const clip=path.join(dir,'base.mp4'),audio=path.join(dir,'narration.wav');
  execFileSync('ffmpeg',['-v','error','-y','-f','lavfi','-i','testsrc2=s=240x320:r=24:d=10','-c:v','libx264','-pix_fmt','yuv420p',clip]);
  execFileSync('ffmpeg',['-v','error','-y','-f','lavfi','-i','sine=frequency=440:sample_rate=44100:duration=23',audio]);
  for(const hasAudio of [false,true]){
    const output=path.join(dir,hasAudio?'loop.mp4':'silent.mp4');
    const result=await composeXfade({sourceClips:[clip],audioPath:hasAudio?audio:'',audioDuration:hasAudio?23:0,outputPath:output,fadeDuration:.5,format:'1:1',renderJobId:'test-single-source'});
    assert.equal(result.repeatedClips,hasAudio?3:1);
    assert(Math.abs(await probeDuration(output)-(hasAudio?23:10))<.15);
    const probe=JSON.parse(execFileSync('ffprobe',['-v','error','-show_streams','-of','json',output],{encoding:'utf8'}));
    const video=probe.streams.find(s=>s.codec_type==='video');
    assert.equal(video.r_frame_rate,'24/1'); assert.equal(video.sample_aspect_ratio,'1:1');
    assert.equal(video.width,video.height); assert.equal(probe.streams.filter(s=>s.codec_type==='audio').length,hasAudio?1:0);
  }
  console.log('FFmpeg single-source native duration and narrated crossfade loop OK:',dir);
})().catch(e=>{console.error(e);process.exitCode=1;});
