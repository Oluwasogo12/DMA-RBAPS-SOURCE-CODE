<?php
require_once 'includes/auth.php';
requireLogin();
$pageTitle = 'Brain Games — RBAPS';
include 'includes/header.php';
?>

<div class="section">
  <div class="section-header">
    <h2><i class="fa-solid fa-gamepad" style="color:var(--gold);margin-right:.5rem"></i> Brain Relaxation Zone</h2>
    <p>Take a mindful break — these games calm the mind and sharpen focus between study sessions.</p>
  </div>

  <!-- Game Tabs -->
  <div class="games-tab-bar" role="tablist" aria-label="Brain Games">
    <button class="game-tab active" data-game="memory" role="tab" aria-selected="true" aria-controls="game-memory">
      <i class="fa-solid fa-brain"></i>
      <span>Memory Match</span>
    </button>
    <button class="game-tab" data-game="breathe" role="tab" aria-selected="false" aria-controls="game-breathe">
      <i class="fa-solid fa-wind"></i>
      <span>Zen Breathe</span>
    </button>
    <button class="game-tab" data-game="pattern" role="tab" aria-selected="false" aria-controls="game-pattern">
      <i class="fa-solid fa-shapes"></i>
      <span>Pattern Flow</span>
    </button>
  </div>

  <!-- ═══════════════════════════════════════
       GAME 1: MEMORY MATCH
  ═══════════════════════════════════════ -->
  <div id="game-memory" class="game-panel active" role="tabpanel">
    <div class="game-card">
      <div class="game-header">
        <div>
          <h3 class="game-title">Memory Match</h3>
          <p class="game-desc">Flip cards, find matching pairs. Trains focus and working memory.</p>
        </div>
        <div class="game-meta">
          <div class="game-stat"><span id="mem-moves">0</span><small>Moves</small></div>
          <div class="game-stat"><span id="mem-pairs">0</span><small>Pairs</small></div>
          <div class="game-stat"><span id="mem-timer">0:00</span><small>Time</small></div>
        </div>
      </div>

      <div id="mem-board" class="mem-board" aria-label="Memory card grid"></div>

      <div class="game-controls">
        <button class="btn btn-primary" id="mem-restart"><i class="fa-solid fa-rotate-right"></i> New Game</button>
        <select class="game-select" id="mem-difficulty">
          <option value="easy">Easy (4×3)</option>
          <option value="medium" selected>Medium (4×4)</option>
          <option value="hard">Hard (5×4)</option>
        </select>
      </div>

      <div id="mem-win" class="game-win-banner hidden">
        <div class="win-icon"><i class="fa-solid fa-star"></i></div>
        <h4>Brilliant! 🎉</h4>
        <p id="mem-win-msg"></p>
        <button class="btn btn-primary" id="mem-win-restart">Play Again</button>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════
       GAME 2: ZEN BREATHE
  ═══════════════════════════════════════ -->
  <div id="game-breathe" class="game-panel hidden" role="tabpanel">
    <div class="game-card breathe-card">
      <div class="game-header">
        <div>
          <h3 class="game-title">Zen Breathe</h3>
          <p class="game-desc">A guided breathing exercise to relax your nervous system and refresh concentration.</p>
        </div>
        <div class="game-meta">
          <div class="game-stat"><span id="br-cycles">0</span><small>Cycles</small></div>
          <div class="game-stat"><span id="br-time">0:00</span><small>Session</small></div>
        </div>
      </div>

      <div class="breathe-center">
        <div class="breathe-outer-ring">
          <div class="breathe-ring" id="breathe-ring">
            <div class="breathe-core" id="breathe-core">
              <span class="breathe-label" id="breathe-label">Ready</span>
            </div>
          </div>
        </div>
        <div class="breathe-instruction" id="breathe-instruction">Choose a pattern and press Start</div>
      </div>

      <div class="game-controls" style="justify-content:center;gap:1rem;flex-wrap:wrap">
        <select class="game-select" id="br-pattern">
          <option value="4-4-4-4">Box Breathing (4-4-4-4)</option>
          <option value="4-7-8">4-7-8 Relaxing</option>
          <option value="5-5">Coherent (5-5)</option>
        </select>
        <button class="btn btn-primary" id="br-start"><i class="fa-solid fa-play"></i> Start</button>
        <button class="btn btn-outline" id="br-stop" disabled><i class="fa-solid fa-stop"></i> Stop</button>
      </div>

      <div class="breathe-tips">
        <p><i class="fa-solid fa-lightbulb" style="color:var(--gold)"></i> Breathe in through your nose, out through your mouth. Let your shoulders drop. 2–5 minutes of this resets cortisol and sharpens memory recall.</p>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════
       GAME 3: PATTERN FLOW
  ═══════════════════════════════════════ -->
  <div id="game-pattern" class="game-panel hidden" role="tabpanel">
    <div class="game-card">
      <div class="game-header">
        <div>
          <h3 class="game-title">Pattern Flow</h3>
          <p class="game-desc">Watch the sequence light up, then repeat it. Trains sequential memory and attention.</p>
        </div>
        <div class="game-meta">
          <div class="game-stat"><span id="pf-level">1</span><small>Level</small></div>
          <div class="game-stat"><span id="pf-score">0</span><small>Score</small></div>
          <div class="game-stat"><span id="pf-best">0</span><small>Best</small></div>
        </div>
      </div>

      <div class="pf-arena">
        <div class="pf-grid" id="pf-grid">
          <button class="pf-btn" data-idx="0" aria-label="Pattern tile 1"><i class="fa-solid fa-moon"></i></button>
          <button class="pf-btn" data-idx="1" aria-label="Pattern tile 2"><i class="fa-solid fa-sun"></i></button>
          <button class="pf-btn" data-idx="2" aria-label="Pattern tile 3"><i class="fa-solid fa-star"></i></button>
          <button class="pf-btn" data-idx="3" aria-label="Pattern tile 4"><i class="fa-solid fa-bolt"></i></button>
        </div>
        <div class="pf-status" id="pf-status">Press Start to begin</div>
      </div>

      <div class="game-controls">
        <button class="btn btn-primary" id="pf-start"><i class="fa-solid fa-play"></i> Start</button>
        <button class="btn btn-outline" id="pf-restart" style="display:none"><i class="fa-solid fa-rotate-right"></i> Try Again</button>
      </div>

      <div id="pf-lose" class="game-win-banner hidden" style="--win-color:var(--rose)">
        <div class="win-icon" style="color:var(--rose)"><i class="fa-solid fa-face-sad-tear"></i></div>
        <h4>Not quite!</h4>
        <p id="pf-lose-msg"></p>
        <button class="btn btn-primary" id="pf-lose-restart">Try Again</button>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════
     GAMES CSS
══════════════════════════════════════════ -->
<style>
/* ── Tab Bar ────────────────────────────────── */
.games-tab-bar{
  display:flex;gap:.75rem;margin-bottom:1.5rem;
  overflow-x:auto;padding-bottom:.25rem;
}
.game-tab{
  display:flex;align-items:center;gap:.5rem;
  padding:.6rem 1.2rem;border-radius:var(--radius-pill);
  border:1.5px solid var(--border);background:var(--card);
  color:var(--text2);font-size:.9rem;font-weight:600;
  cursor:pointer;white-space:nowrap;transition:all var(--t-base) var(--ease-out);
}
.game-tab:hover{border-color:var(--border-hover);color:var(--text)}
.game-tab.active{
  background:var(--gold-dim);border-color:var(--gold);
  color:var(--gold);box-shadow:0 0 0 2px var(--gold-glow);
}

/* ── Game Panel ─────────────────────────────── */
.game-panel{animation:fadeUp .35s var(--ease-out)}
.game-panel.hidden{display:none}
@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}

/* ── Game Card ──────────────────────────────── */
.game-card{
  background:var(--card);border:1px solid var(--border);
  border-radius:var(--radius);padding:2rem;position:relative;overflow:hidden;
}
.game-card::before{
  content:'';position:absolute;top:0;left:0;right:0;height:2px;
  background:var(--grad-gold);opacity:.6;
}

/* ── Game Header ────────────────────────────── */
.game-header{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;margin-bottom:1.75rem;flex-wrap:wrap}
.game-title{font-size:1.25rem;font-weight:700;color:var(--text);margin:0 0 .3rem}
.game-desc{color:var(--text2);font-size:.9rem;margin:0}
.game-meta{display:flex;gap:1rem}
.game-stat{display:flex;flex-direction:column;align-items:center;min-width:52px;
  background:var(--bg2);border-radius:var(--radius-sm);padding:.5rem .75rem;
  border:1px solid var(--border);
}
.game-stat span{font-size:1.2rem;font-weight:700;color:var(--gold);font-family:'DM Mono',monospace}
.game-stat small{font-size:.7rem;color:var(--text3);margin-top:.1rem}

.game-controls{display:flex;gap:.75rem;align-items:center;margin-top:1.5rem;flex-wrap:wrap}
.game-select{
  padding:.55rem .9rem;border-radius:var(--radius-sm);
  background:var(--bg2);border:1.5px solid var(--border);
  color:var(--text);font-size:.875rem;cursor:pointer;
  transition:border-color var(--t-base);
}
.game-select:hover{border-color:var(--border-hover)}
.game-select:focus{outline:none;border-color:var(--gold)}

/* ── Win / Lose Banner ──────────────────────── */
.game-win-banner{
  position:absolute;inset:0;background:rgba(13,15,20,.92);
  backdrop-filter:blur(6px);border-radius:var(--radius);
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  gap:.75rem;z-index:10;text-align:center;padding:2rem;
  animation:fadeUp .4s var(--ease-out);
  --win-color:var(--gold);
}
.game-win-banner.hidden{display:none}
.win-icon{font-size:3.5rem;color:var(--win-color);animation:pulse 1.2s ease-in-out infinite}
@keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.12)}}
.game-win-banner h4{font-size:1.6rem;font-weight:700;color:var(--text);margin:0}
.game-win-banner p{color:var(--text2);max-width:280px}

/* ═══════════════════════════════════
   GAME 1 — MEMORY MATCH
═══════════════════════════════════ */
.mem-board{
  display:grid;gap:.65rem;margin:0 auto;
  width:100%;max-width:500px;
}
.mem-card{
  aspect-ratio:1;border-radius:var(--radius-sm);
  background:var(--bg2);border:1.5px solid var(--border);
  cursor:pointer;perspective:600px;transition:transform var(--t-base);
  position:relative;
}
.mem-card:hover:not(.flipped):not(.matched){
  border-color:var(--border-hover);transform:translateY(-2px);
}
.mem-card-inner{
  width:100%;height:100%;position:relative;
  transform-style:preserve-3d;transition:transform .4s var(--ease-out);
  border-radius:var(--radius-sm);
}
.mem-card.flipped .mem-card-inner,.mem-card.matched .mem-card-inner{
  transform:rotateY(180deg);
}
.mem-front,.mem-back{
  position:absolute;inset:0;backface-visibility:hidden;
  display:flex;align-items:center;justify-content:center;
  border-radius:var(--radius-sm);
}
.mem-front{
  background:var(--bg3);
  background-image:repeating-linear-gradient(
    45deg, transparent, transparent 5px,
    rgba(201,168,76,.04) 5px, rgba(201,168,76,.04) 6px
  );
}
.mem-front-icon{font-size:1.4rem;color:var(--text4)}
.mem-back{
  background:var(--bg4);transform:rotateY(180deg);
  font-size:2rem;
  border:1.5px solid var(--border-hover);
}
.mem-card.matched .mem-back{
  background:rgba(61,214,140,.12);border-color:var(--jade);
  animation:matchPop .4s var(--ease-spring);
}
@keyframes matchPop{0%{transform:rotateY(180deg) scale(1)}50%{transform:rotateY(180deg) scale(1.1)}100%{transform:rotateY(180deg) scale(1)}}

/* ═══════════════════════════════════
   GAME 2 — ZEN BREATHE
═══════════════════════════════════ */
.breathe-card{overflow:hidden}
.breathe-center{display:flex;flex-direction:column;align-items:center;gap:1.25rem;padding:1.5rem 0}
.breathe-outer-ring{
  width:220px;height:220px;border-radius:50%;
  background:conic-gradient(var(--gold-dim) 0deg, transparent 360deg);
  display:flex;align-items:center;justify-content:center;
  animation:slowSpin 12s linear infinite;
}
@keyframes slowSpin{to{transform:rotate(360deg)}}
.breathe-ring{
  width:200px;height:200px;border-radius:50%;
  border:2px solid var(--border);
  display:flex;align-items:center;justify-content:center;
  background:var(--bg);position:relative;
  transition:all 1s ease;
  box-shadow:inset 0 0 30px rgba(0,0,0,.4);
  animation:none;
}
.breathe-ring.inhale{
  box-shadow:0 0 40px var(--gold-glow),inset 0 0 20px rgba(0,0,0,.2);
  border-color:var(--gold);
}
.breathe-ring.hold{
  box-shadow:0 0 30px rgba(78,201,176,.25),inset 0 0 20px rgba(0,0,0,.2);
  border-color:var(--cyan);
}
.breathe-ring.exhale{
  box-shadow:0 0 20px rgba(123,92,240,.18),inset 0 0 30px rgba(0,0,0,.4);
  border-color:var(--lavender);
}
.breathe-core{
  width:120px;height:120px;border-radius:50%;
  background:radial-gradient(circle at 40% 35%, var(--bg4), var(--bg2));
  border:1px solid var(--border);
  display:flex;align-items:center;justify-content:center;
  transition:all 1s ease;
}
.breathe-core.inhale{
  width:150px;height:150px;
  background:radial-gradient(circle at 40% 35%, var(--gold-dim), var(--bg2));
}
.breathe-core.exhale{width:100px;height:100px}
.breathe-label{
  font-size:.95rem;font-weight:700;color:var(--gold);
  text-transform:uppercase;letter-spacing:.08em;
  font-family:'DM Mono',monospace;
}
.breathe-instruction{
  font-size:1.1rem;color:var(--text2);font-weight:500;
  text-align:center;min-height:1.6em;transition:all .5s;
}
.breathe-tips{
  margin-top:1.5rem;padding:1rem 1.25rem;
  background:var(--gold-dim);border-radius:var(--radius-sm);
  border-left:3px solid var(--gold);
}
.breathe-tips p{margin:0;font-size:.85rem;color:var(--text2);line-height:1.6}

/* ═══════════════════════════════════
   GAME 3 — PATTERN FLOW
═══════════════════════════════════ */
.pf-arena{display:flex;flex-direction:column;align-items:center;gap:1.5rem;padding:1rem 0}
.pf-grid{
  display:grid;grid-template-columns:1fr 1fr;
  gap:1rem;width:260px;
}
.pf-btn{
  aspect-ratio:1;border-radius:var(--radius);
  border:2px solid var(--border);background:var(--bg2);
  display:flex;align-items:center;justify-content:center;
  font-size:2rem;cursor:pointer;
  transition:all var(--t-base) var(--ease-out);
  position:relative;overflow:hidden;
}
.pf-btn:nth-child(1){color:#f5dfa0}
.pf-btn:nth-child(2){color:#4ec9b0}
.pf-btn:nth-child(3){color:#a78bfa}
.pf-btn:nth-child(4){color:#f05570}
.pf-btn::after{
  content:'';position:absolute;inset:0;
  border-radius:inherit;transition:opacity var(--t-base);
  opacity:0;
}
.pf-btn:nth-child(1)::after{background:rgba(245,223,160,.18)}
.pf-btn:nth-child(2)::after{background:rgba(78,201,176,.18)}
.pf-btn:nth-child(3)::after{background:rgba(167,139,250,.18)}
.pf-btn:nth-child(4)::after{background:rgba(240,85,112,.18)}
.pf-btn.lit::after{opacity:1}
.pf-btn.lit{
  border-color:currentColor;
  box-shadow:0 0 24px currentColor;
  transform:scale(.96);
}
.pf-btn:hover:not(:disabled){transform:scale(.97);border-color:var(--border-hover)}
.pf-btn:disabled{cursor:not-allowed}
.pf-status{
  font-size:1rem;font-weight:600;color:var(--text2);
  text-align:center;min-height:1.5em;font-family:'DM Mono',monospace;
}
.pf-status.watching{color:var(--gold)}
.pf-status.your-turn{color:var(--cyan)}
.pf-status.wrong{color:var(--rose)}

/* Responsive */
@media(max-width:600px){
  .game-header{flex-direction:column}
  .game-card{padding:1.35rem 1rem}
  .games-tab-bar{gap:.45rem}
  .game-tab{padding:.5rem .9rem;font-size:.82rem}
  .breathe-outer-ring{width:180px;height:180px}
  .breathe-ring{width:160px;height:160px}
  .breathe-core{width:90px;height:90px}
  .breathe-core.inhale{width:115px;height:115px}
  .pf-grid{width:210px;gap:.75rem}
  .mem-board{max-width:340px}
  .game-meta{gap:.5rem;flex-wrap:wrap}
  .game-stat{min-width:44px;padding:.4rem .55rem}
}
@media(max-width:380px){
  .game-card{padding:1rem .75rem}
  .mem-board{max-width:280px}
  .pf-grid{width:180px;gap:.5rem}
  .breathe-outer-ring{width:150px;height:150px}
  .breathe-ring{width:132px;height:132px}
  .breathe-core{width:76px;height:76px}
  .breathe-core.inhale{width:96px;height:96px}
}
</style>

<!-- ══════════════════════════════════════════
     GAMES JAVASCRIPT
══════════════════════════════════════════ -->
<script>
/* ─── Tab Switcher ──────────────────────────── */
document.querySelectorAll('.game-tab').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.game-tab').forEach(t => {t.classList.remove('active');t.setAttribute('aria-selected','false')});
    document.querySelectorAll('.game-panel').forEach(p => p.classList.add('hidden'));
    btn.classList.add('active');
    btn.setAttribute('aria-selected','true');
    document.getElementById('game-' + btn.dataset.game).classList.remove('hidden');
  });
});

/* ════════════════════════════════════════
   GAME 1 — MEMORY MATCH
════════════════════════════════════════ */
(function(){
  const EMOJIS = ['🌙','⭐','🎯','🔥','🌈','🍀','🦋','🌺','🎵','🏆','🌊','💎','🎨','🔮','🌸','🦉','🐬','🌻','🎭','🦚'];
  let cards=[], flipped=[], matched=0, moves=0, totalPairs=0, timer=null, secs=0, locked=false;

  const board = document.getElementById('mem-board');
  const movEl = document.getElementById('mem-moves');
  const pairEl = document.getElementById('mem-pairs');
  const timeEl = document.getElementById('mem-timer');
  const winEl  = document.getElementById('mem-win');
  const winMsg = document.getElementById('mem-win-msg');

  function getConfig(){
    const d = document.getElementById('mem-difficulty').value;
    if(d==='easy')  return {cols:4,rows:3};
    if(d==='hard')  return {cols:5,rows:4};
    return {cols:4,rows:4};
  }

  function startGame(){
    clearInterval(timer);
    secs=0; moves=0; matched=0; flipped=[]; locked=false;
    winEl.classList.add('hidden');
    movEl.textContent='0'; pairEl.textContent='0'; timeEl.textContent='0:00';

    const {cols,rows} = getConfig();
    totalPairs = (cols*rows)/2;
    board.style.gridTemplateColumns = `repeat(${cols},1fr)`;
    board.style.maxWidth = cols<=4 ? '400px' : '500px';

    const pool = EMOJIS.slice(0,totalPairs);
    const deck = [...pool,...pool].sort(()=>Math.random()-.5);
    board.innerHTML='';
    cards=[];
    deck.forEach((emoji,i)=>{
      const c = document.createElement('div');
      c.className='mem-card';
      c.dataset.emoji=emoji;
      c.setAttribute('role','button');
      c.setAttribute('aria-label','Hidden card');
      c.innerHTML=`<div class="mem-card-inner">
        <div class="mem-front"><span class="mem-front-icon"><i class="fa-solid fa-question"></i></span></div>
        <div class="mem-back" aria-hidden="true">${emoji}</div>
      </div>`;
      c.addEventListener('click',()=>flip(c));
      board.appendChild(c);
      cards.push(c);
    });

    timer = setInterval(()=>{
      secs++;
      const m=Math.floor(secs/60), s=secs%60;
      timeEl.textContent=`${m}:${s.toString().padStart(2,'0')}`;
    },1000);
  }

  function flip(card){
    if(locked||card.classList.contains('flipped')||card.classList.contains('matched')) return;
    card.classList.add('flipped');
    card.setAttribute('aria-label', card.dataset.emoji);
    flipped.push(card);
    if(flipped.length===2){
      locked=true; moves++;
      movEl.textContent=moves;
      const [a,b]=flipped;
      if(a.dataset.emoji===b.dataset.emoji){
        a.classList.add('matched'); b.classList.add('matched');
        matched++; pairEl.textContent=matched;
        flipped=[]; locked=false;
        if(matched===totalPairs) endGame();
      } else {
        setTimeout(()=>{
          a.classList.remove('flipped'); b.classList.remove('flipped');
          a.setAttribute('aria-label','Hidden card'); b.setAttribute('aria-label','Hidden card');
          flipped=[]; locked=false;
        },900);
      }
    }
  }

  function endGame(){
    clearInterval(timer);
    const m=Math.floor(secs/60), s=secs%60;
    winMsg.textContent=`${moves} moves · ${m}:${s.toString().padStart(2,'0')} — ${moves<=totalPairs+3?'Perfect memory!':moves<=totalPairs+8?'Great job!':'Keep practising!'}`;
    winEl.classList.remove('hidden');
  }

  document.getElementById('mem-restart').addEventListener('click',startGame);
  document.getElementById('mem-win-restart').addEventListener('click',startGame);
  document.getElementById('mem-difficulty').addEventListener('change',startGame);
  startGame();
})();


/* ════════════════════════════════════════
   GAME 2 — ZEN BREATHE
════════════════════════════════════════ */
(function(){
  const ring   = document.getElementById('breathe-ring');
  const core   = document.getElementById('breathe-core');
  const label  = document.getElementById('breathe-label');
  const instr  = document.getElementById('breathe-instruction');
  const cycleEl= document.getElementById('br-cycles');
  const sessEl = document.getElementById('br-time');
  const startBtn= document.getElementById('br-start');
  const stopBtn = document.getElementById('br-stop');
  const patSel  = document.getElementById('br-pattern');

  let running=false, cycleTimer=null, sessTimer=null, cycles=0, sessecs=0;

  const PATTERNS = {
    '4-4-4-4': [{phase:'inhale',dur:4,text:'Breathe In…'},{phase:'hold',dur:4,text:'Hold…'},{phase:'exhale',dur:4,text:'Breathe Out…'},{phase:'hold',dur:4,text:'Hold…'}],
    '4-7-8':   [{phase:'inhale',dur:4,text:'Breathe In…'},{phase:'hold',dur:7,text:'Hold…'},{phase:'exhale',dur:8,text:'Breathe Out…'}],
    '5-5':     [{phase:'inhale',dur:5,text:'Breathe In…'},{phase:'exhale',dur:5,text:'Breathe Out…'}],
  };

  function setPhase(step, steps){
    ring.className='breathe-ring '+step.phase;
    core.className='breathe-core '+step.phase;
    label.textContent=step.dur+'s';
    instr.textContent=step.text;
    let remaining=step.dur;
    return new Promise(resolve=>{
      if(!running){resolve();return;}
      const t=setInterval(()=>{
        remaining--;
        label.textContent=remaining+'s';
        if(remaining<=0||!running){clearInterval(t);resolve();}
      },1000);
    });
  }

  async function runCycle(){
    const steps=PATTERNS[patSel.value];
    while(running){
      for(const step of steps){
        if(!running)break;
        await setPhase(step,steps);
        if(!running)break;
      }
      if(running){cycles++;cycleEl.textContent=cycles;}
    }
  }

  startBtn.addEventListener('click',()=>{
    if(running)return;
    running=true; cycles=0; sessecs=0;
    cycleEl.textContent='0';
    startBtn.disabled=true; stopBtn.disabled=false;
    sessTimer=setInterval(()=>{
      sessecs++;
      const m=Math.floor(sessecs/60),s=sessecs%60;
      sessEl.textContent=`${m}:${s.toString().padStart(2,'0')}`;
    },1000);
    runCycle();
  });

  stopBtn.addEventListener('click',()=>{
    running=false;
    clearInterval(sessTimer);
    startBtn.disabled=false; stopBtn.disabled=true;
    ring.className='breathe-ring';
    core.className='breathe-core';
    label.textContent='Done';
    instr.textContent=`${cycles} cycle${cycles!==1?'s':''} complete. Well done 🌿`;
  });
})();


/* ════════════════════════════════════════
   GAME 3 — PATTERN FLOW
════════════════════════════════════════ */
(function(){
  const btns     = document.querySelectorAll('.pf-btn');
  const status   = document.getElementById('pf-status');
  const levelEl  = document.getElementById('pf-level');
  const scoreEl  = document.getElementById('pf-score');
  const bestEl   = document.getElementById('pf-best');
  const loseEl   = document.getElementById('pf-lose');
  const loseMsgEl= document.getElementById('pf-lose-msg');
  const startBtn = document.getElementById('pf-start');
  const restartBtn=document.getElementById('pf-restart');

  let sequence=[], userSeq=[], level=1, score=0, best=+localStorage.getItem('rbaps-pf-best')||0, playing=false;
  bestEl.textContent=best;

  function sleep(ms){return new Promise(r=>setTimeout(r,ms));}

  async function flashBtn(idx){
    btns[idx].classList.add('lit');
    await sleep(500);
    btns[idx].classList.remove('lit');
    await sleep(250);
  }

  async function showSequence(){
    btns.forEach(b=>b.disabled=true);
    status.className='pf-status watching';
    status.textContent='Watch the pattern…';
    await sleep(700);
    for(const idx of sequence){ await flashBtn(idx);}
    status.className='pf-status your-turn';
    status.textContent='Your turn!';
    btns.forEach(b=>b.disabled=false);
    userSeq=[];
  }

  async function startGame(){
    sequence=[]; level=1; score=0;
    scoreEl.textContent='0'; levelEl.textContent='1';
    loseEl.classList.add('hidden');
    startBtn.style.display='none';
    restartBtn.style.display='none';
    playing=true;
    addAndShow();
  }

  function addAndShow(){
    sequence.push(Math.floor(Math.random()*4));
    levelEl.textContent=level;
    showSequence();
  }

  btns.forEach((btn,i)=>{
    btn.addEventListener('click',async()=>{
      if(!playing)return;
      btn.classList.add('lit');
      await sleep(300);
      btn.classList.remove('lit');

      userSeq.push(i);
      const pos=userSeq.length-1;

      if(userSeq[pos]!==sequence[pos]){
        // Wrong
        btns.forEach(b=>b.disabled=true);
        playing=false;
        status.className='pf-status wrong';
        status.textContent='Wrong!';
        if(score>best){best=score;localStorage.setItem('rbaps-pf-best',best);bestEl.textContent=best;}
        await sleep(600);
        loseMsgEl.textContent=`You reached level ${level} with a score of ${score}. Best: ${best}`;
        loseEl.classList.remove('hidden');
        return;
      }

      if(userSeq.length===sequence.length){
        // Correct round
        btns.forEach(b=>b.disabled=true);
        score+=level*10; scoreEl.textContent=score;
        status.textContent='✓ Correct!';
        level++;
        await sleep(900);
        if(playing) addAndShow();
      }
    });
  });

  startBtn.addEventListener('click',startGame);
  restartBtn.addEventListener('click',startGame);
  document.getElementById('pf-lose-restart').addEventListener('click',()=>{loseEl.classList.add('hidden');startGame();});
})();
</script>

<?php include 'includes/footer.php'; ?>
