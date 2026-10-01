const assert=require('node:assert/strict');
const fs=require('node:fs');
const os=require('node:os');
const path=require('node:path');
const {execFileSync}=require('node:child_process');
const {validateRenderBody,buildSequence,buildXfadeFilter,composeXfade,addBackgroundMusicToVideo,probeDuration,targetSettings,wrapCaptionText,applyListingRules}=require('../stlai-video-renderer/server');
const base={format:'1:1',narration_enabled:false,clips:[{index:1,url:'https://example.test/base.mp4'}],video_generation_mode:'reference_video'};
assert.equal(validateRenderBody(base).clips.length,1);
assert.throws(()=>validateRenderBody({...base,video_generation_mode:'clips'}));
assert.throws(()=>validateRenderBody({...base,clips:[...base.clips,...base.clips]}));
assert.equal(validateRenderBody({...base,video_generation_mode:'clips',clips:Array.from({length:4},(_,i)=>({index:i+1,url:`https://example.test/${i}.mp4`}))}).clips.length,4);
const positioned=validateRenderBody({...base,marketplace:'etsy',on_screen_text:['Optional caption'],on_screen_text_position:'center',background_music_enabled:false,background_music_url:'https://example.test/music.mp3'});
assert.deepEqual(positioned.onScreenText,['Optional caption'],'renderer accepts optional Etsy text');
assert.equal(positioned.onScreenTextPosition,'center');
assert.equal(positioned.backgroundMusicEnabled,false,'explicit music off wins over configured URL');
assert.equal(validateRenderBody({...base,on_screen_text_position:'invalid'}).onScreenTextPosition,'bottom');
const wrappedCaption=wrapCaptionText('Uma escultura contemporânea da Sagrada Família feita para decorar diferentes ambientes',28);
assert(wrappedCaption.includes('\n'),'long captions wrap instead of overflowing horizontally');
assert(wrappedCaption.split('\n').every(line=>line.length<=28),'caption lines respect the calculated safe width');
const repeated=buildSequence([{path:'base.mp4',duration:10}],26,.5);
assert.equal(repeated.length,3);
const filter=buildXfadeFilter(repeated,.5,targetSettings('1:1'));
assert(filter.includes('offset=9.5') && filter.includes('offset=19'));
const real=buildXfadeFilter([{duration:7},{duration:6},{duration:9},{duration:8}],.5);
assert(real.includes('offset=6.5') && real.includes('offset=12') && real.includes('offset=20.5'));
const etsyFilter=buildXfadeFilter([{duration:15}],.5,targetSettings('9:16'));
assert(etsyFilter.includes('force_original_aspect_ratio=increase') && etsyFilter.includes('crop=') && !etsyFilter.includes('pad='),'native Etsy output fills 9:16 without black padding');
const etsySettings=targetSettings('9:16');
assert(Math.min(etsySettings.width,etsySettings.height)>=500,'Etsy output always meets the 500px minimum');
assert.equal(etsySettings.width*16,etsySettings.height*9,'Etsy output preserves exact 9:16');
const constrainedSettings=JSON.parse(execFileSync(process.execPath,['-e',`const {targetSettings}=require(${JSON.stringify(path.resolve(__dirname,'../stlai-video-renderer/server'))});process.stdout.write(JSON.stringify(targetSettings('9:16')));`],{
  encoding:'utf8',
  env:{...process.env,RENDER_OUTPUT_QUALITY:'preview',RENDER_PREVIEW_WIDTH_9_16:'480',RENDER_PREVIEW_HEIGHT_9_16:'854'}
}));
assert.equal(constrainedSettings.width,504,'a 480px preview is raised to the first safe exact 9:16 width');
assert.equal(constrainedSettings.height,896,'a 480px preview is raised to the matching exact 9:16 height');
if(!process.argv.includes('--render')){ console.log('Renderer reference-video contracts OK'); process.exit(0); }
(async()=>{
  const dir=fs.mkdtempSync(path.join(os.tmpdir(),'stlai-reference-render-'));
  const clip=path.join(dir,'base.mp4'),audio=path.join(dir,'narration.wav');
  execFileSync('ffmpeg',['-v','error','-y','-f','lavfi','-i','testsrc2=s=270x480:r=24:d=15','-f','lavfi','-i','sine=frequency=220:sample_rate=44100:duration=15','-shortest','-c:v','libx264','-pix_fmt','yuv420p','-c:a','aac',clip]);
  execFileSync('ffmpeg',['-v','error','-y','-f','lavfi','-i','sine=frequency=440:sample_rate=44100:duration=23',audio]);
  for(const hasAudio of [false,true]){
    const output=path.join(dir,hasAudio?'loop.mp4':'silent.mp4');
    const format=hasAudio?'1:1':'9:16';
    const result=await composeXfade({sourceClips:[clip],audioPath:hasAudio?audio:'',audioDuration:hasAudio?23:0,outputPath:output,fadeDuration:.5,format,renderJobId:'test-single-source'});
    assert.equal(result.repeatedClips,hasAudio?2:1);
    assert(Math.abs(await probeDuration(output)-(hasAudio?23:15))<.15);
    const probe=JSON.parse(execFileSync('ffprobe',['-v','error','-show_streams','-of','json',output],{encoding:'utf8'}));
    const video=probe.streams.find(s=>s.codec_type==='video');
    assert.equal(video.r_frame_rate,'24/1'); assert.equal(video.sample_aspect_ratio,'1:1');
    if(hasAudio) assert.equal(video.width,video.height);
    else assert.equal(video.width*16,video.height*9);
    assert.equal(probe.streams.filter(s=>s.codec_type==='audio').length,hasAudio?1:0);
  }
  const silent=path.join(dir,'silent.mp4'),musicVideo=path.join(dir,'music-only.mp4');
  const hasDrawtext=/\bdrawtext\b/.test(execFileSync('ffmpeg',['-hide_banner','-filters'],{encoding:'utf8'}));
  if(hasDrawtext){
    const captioned=path.join(dir,'captioned.mp4');
    await applyListingRules({
      inputPath:silent,
      outputPath:captioned,
      workDir:dir,
      format:'9:16',
      maxDuration:15,
      onScreenText:['Uma escultura contemporânea da Sagrada Família feita para decorar diferentes ambientes sem cortar palavras nas laterais'],
      onScreenTextPosition:'top',
      preserveAudio:false
    });
    const captionProbe=JSON.parse(execFileSync('ffprobe',['-v','error','-show_streams','-show_format','-of','json',captioned],{encoding:'utf8'}));
    const captionVideo=captionProbe.streams.find(s=>s.codec_type==='video');
    assert.equal(captionVideo.width*16,captionVideo.height*9,'caption render preserves exact 9:16');
    assert.equal(captionProbe.streams.filter(s=>s.codec_type==='audio').length,0,'caption render remains silent');
  }
  await addBackgroundMusicToVideo({videoPath:silent,musicPath:audio,outputPath:musicVideo,musicVolume:.06});
  const musicProbe=JSON.parse(execFileSync('ffprobe',['-v','error','-show_streams','-show_format','-of','json',musicVideo],{encoding:'utf8'}));
  assert(Math.abs(Number(musicProbe.format.duration)-15)<.15,'music-only output preserves the complete video duration');
  assert.equal(musicProbe.streams.filter(s=>s.codec_type==='audio').length,1,'music-only output has one soundtrack');
  console.log(`FFmpeg single-source, caption wrapping${hasDrawtext?' rendered':' validated without local drawtext'}, narration and optional music contracts OK:`,dir);
})().catch(e=>{console.error(e);process.exitCode=1;});
