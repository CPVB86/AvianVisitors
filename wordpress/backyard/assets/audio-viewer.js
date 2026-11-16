function backyardAudioOriginalTime(playbackTime, factor, duration) {
    return Math.max(0, Math.min(duration, playbackTime / factor));
}
(() => {
    function init() { document.querySelectorAll('.backyard-audio-viewer').forEach(box => {
        if (box.dataset.initialized) return;
        box.dataset.initialized='1';
        const button = box.querySelector('button'), message = box.querySelector('.backyard-audio-message');
        const audio = box.querySelector('audio'), canvas = box.querySelector('canvas'), ctx = canvas.getContext('2d');
        let data, picture;
        function paint() {
            if (!data || !picture) return;
            const x = 58, y = 38, w = 726, h = 224, duration = data.duration_seconds;
            ctx.fillStyle='#10151c'; ctx.fillRect(0,0,800,330);
            ctx.drawImage(picture,x,y,w,h);
            const window = data.detection_window;
            if (window) {
                const left = x + Math.max(0,window.start_seconds)/duration*w;
                const right = x + Math.min(duration,window.end_seconds)/duration*w;
                if (right > left) {
                    ctx.fillStyle='#64c7ff'; ctx.font='14px sans-serif';
                    ctx.fillText('Detectievenster',x,17);
                    ctx.fillRect(left,25,right-left,4);
                }
            }
            ctx.font='12px sans-serif';ctx.fillStyle='#fff';
            for(let i=0;i<=4;i++){
                ctx.fillText((duration*i/4).toFixed(1)+' s',x+i*w/4-14,283);
                ctx.fillText((data.frequency_max_hz/1000*(1-i/4)).toFixed(0)+' kHz',2,y+i*h/4+9);
            }
            ctx.strokeStyle='#aaa';ctx.beginPath();
            data.waveform.forEach((v,i)=>{const px=x+i*w/(data.waveform.length-1);ctx.moveTo(px,309-v*15);ctx.lineTo(px,309+v*15);});ctx.stroke();
            const pos = backyardAudioOriginalTime(audio.currentTime,data.time_expansion,duration);
            ctx.strokeStyle='#fff';ctx.beginPath();ctx.moveTo(x+pos/duration*w,y);ctx.lineTo(x+pos/duration*w,y+h);ctx.stroke();
        }
        audio.addEventListener('timeupdate',paint);
        audio.addEventListener('seeked',paint);
        button.addEventListener('click',async()=>{
            button.disabled=true;message.textContent='Luisterversie en spectrogram laden…';
            try{
                const metadata=new URL(box.dataset.url);metadata.searchParams.set('part','metadata');
                const response=await fetch(metadata,{credentials:'same-origin',cache:'no-store'});
                if(!response.ok) throw Error();
                data=await response.json();
                if(!(data.duration_seconds>0)||![1,10].includes(data.time_expansion)||!Array.isArray(data.waveform)) throw Error();
                picture=new Image();
                await new Promise((resolve,reject)=>{picture.onload=resolve;picture.onerror=reject;picture.src='data:image/png;base64,'+data.spectrogram_png;});
                const listen=new URL(box.dataset.url);listen.searchParams.set('part','listen');audio.src=listen;
                box.querySelector('.backyard-audio-display').hidden=false;
                box.querySelector('.backyard-audio-caption').textContent=
                    (data.time_expansion===10?'Bats: 10× vertraagd. ':'Normale snelheid. ')+
                    'Volumecorrectie: '+data.gain_db+' dB. Tijd/frequentie-as: originele opname. '+
                    'Blauw balkje boven de grafiek: detectievenster, geen exact herkenningspunt. Kleur: −90 tot 0 dBFS.';
                message.textContent='';button.hidden=true;paint();
            }catch(e){message.textContent='Audio-preview niet beschikbaar. Controleer of de opname nog aanwezig en toegankelijk is.';button.disabled=false;}
        });
        audio.addEventListener('error',()=>{message.textContent='De luisterversie kon niet worden afgespeeld.';});
    }); }
    init();
    new MutationObserver(init).observe(document.body,{childList:true,subtree:true});
})();
