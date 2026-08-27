<?php

declare(strict_types=1);

namespace PhpFlow\Exporter;

use PhpFlow\Domain\Graph\Graph;

final readonly class HtmlExporter
{
    public function __construct(
        private JsonExporter $jsonExporter = new JsonExporter(),
    ) {
    }

    public function export(Graph $graph): string
    {
        $json = trim($this->jsonExporter->export($graph));
        $safeJson = str_replace('</script', '<\/script', $json);

        return str_replace(
            '__PHPFLOW_GRAPH__',
            $safeJson,
            $this->template(),
        );
    }

    private function template(): string
    {
        return <<<'HTML'
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>PHPFlow Graph</title>
<style>
:root{font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#18212f;background:#f5f7fa}
*{box-sizing:border-box}body{margin:0;height:100vh;overflow:hidden}.app{display:grid;grid-template-columns:280px 1fr 340px;height:100vh}
aside{background:#fff;border-right:1px solid #dfe4ea;padding:18px;overflow:auto}.details{border-right:0;border-left:1px solid #dfe4ea}
h1{font-size:20px;margin:0 0 4px}.muted{color:#687386;font-size:12px}.toolbar{display:flex;gap:8px;margin:14px 0}
button{border:1px solid #cbd3dd;background:#fff;border-radius:7px;padding:7px 10px;cursor:pointer}button:hover{background:#f1f4f8}
.filter{display:flex;align-items:center;gap:8px;padding:5px 0;font-size:13px}.swatch{width:10px;height:10px;border-radius:50%;display:inline-block}
main{position:relative;overflow:hidden}.canvas{width:100%;height:100%;cursor:grab}.canvas.dragging{cursor:grabbing}
.edge{stroke:#aeb7c4;stroke-width:1.3}.node circle{stroke:#fff;stroke-width:2}.node text{font-size:11px;pointer-events:none;fill:#263244}.node.selected circle{stroke:#111827;stroke-width:4}.node.dimmed{opacity:.18}.edge.highlighted{stroke:#334155;stroke-width:2.6}.edge.dimmed{opacity:.12}.toggle{cursor:pointer}.toggle text{font-size:13px;font-weight:700;fill:#fff;text-anchor:middle;dominant-baseline:central}.nav-actions{display:flex;flex-wrap:wrap;gap:6px;margin-top:12px}.nav-actions button{font-size:11px}.hidden-count{font-size:10px;fill:#64748b}
.empty{padding:16px;color:#687386}.kv{font-size:12px;margin:8px 0}.kv strong{display:block;color:#687386;margin-bottom:2px}.json{white-space:pre-wrap;word-break:break-word;font:11px ui-monospace,SFMono-Regular,Menlo,monospace;background:#f6f8fa;padding:10px;border-radius:7px}
.legend-title{font-size:12px;font-weight:700;margin:18px 0 5px}.badge{display:inline-block;padding:3px 7px;border-radius:999px;background:#eef2f7;font-size:11px}
</style>
</head>
<body>
<div class="app">
<aside>
<h1>PHPFlow</h1>
<div class="muted" id="stats"></div>
<div class="toolbar"><button id="fit">Fit graph</button><button id="reset">Reset</button></div>
<div class="legend-title">Node types</div>
<div id="filters"></div>
<div class="legend-title">Navigation</div>
<div class="muted">Drag to pan and use the mouse wheel to zoom. Click a node to inspect it. Use the +/− control to collapse or expand descendants. The details panel can focus a branch, show direct connections, or jump back to its entry point.</div>
</aside>
<main><svg class="canvas" id="canvas"></svg></main>
<aside class="details">
<h1>Node details</h1>
<div id="details" class="empty">Select a node in the graph.</div>
</aside>
</div>
<script type="application/json" id="phpflow-data">__PHPFLOW_GRAPH__</script>
<script>
const graph=JSON.parse(document.getElementById('phpflow-data').textContent);
const svg=document.getElementById('canvas'),NS='http://www.w3.org/2000/svg';
const colors={route:'#2563eb',controller:'#7c3aed',service:'#0891b2',repository:'#0f766e',handler:'#9333ea',message:'#c026d3',database:'#16a34a',http_endpoint:'#ea580c',exception:'#dc2626',condition:'#ca8a04',return:'#64748b',loop:'#a16207',branch:'#a16207'};
const types=[...new Set(graph.nodes.map(n=>n.type))].sort(),enabled=new Set(types);
const nodeById=new Map(graph.nodes.map(n=>[n.id,n]));
const outgoing=new Map(),incoming=new Map();
graph.nodes.forEach(n=>{outgoing.set(n.id,[]);incoming.set(n.id,[])});
graph.edges.forEach(e=>{if(outgoing.has(e.source))outgoing.get(e.source).push(e.target);if(incoming.has(e.target))incoming.get(e.target).push(e.source)});
const width=()=>svg.clientWidth||900,height=()=>svg.clientHeight||700;
let scale=1,tx=0,ty=0,drag=null,selected=null,focusRoot=null,directOnly=false;
const collapsed=new Set(),positions=new Map();

function el(name,attrs={}){const x=document.createElementNS(NS,name);for(const[k,v]of Object.entries(attrs))x.setAttribute(k,v);return x}
function descendants(id){const found=new Set(),q=[...(outgoing.get(id)||[])];while(q.length){const x=q.shift();if(found.has(x)||x===id)continue;found.add(x);q.push(...(outgoing.get(x)||[]))}return found}
function ancestors(id){const found=new Set(),q=[...(incoming.get(id)||[])];while(q.length){const x=q.shift();if(found.has(x)||x===id)continue;found.add(x);q.push(...(incoming.get(x)||[]))}return found}
function entryPointFor(id){
 const candidates=[id,...ancestors(id)].map(x=>nodeById.get(x)).filter(Boolean).filter(n=>n.metadata?.entryPoint);
 if(!candidates.length)return null;
 const distance=new Map([[id,0]]),q=[id];while(q.length){const x=q.shift(),d=distance.get(x);for(const parent of incoming.get(x)||[]){if(!distance.has(parent)){distance.set(parent,d+1);q.push(parent)}}}
 candidates.sort((a,b)=>(distance.get(a.id)??9999)-(distance.get(b.id)??9999));return candidates[0];
}
function visibleNodes(){
 let ids=new Set(graph.nodes.filter(n=>enabled.has(n.type)).map(n=>n.id));
 if(focusRoot&&ids.has(focusRoot)){const keep=descendants(focusRoot);keep.add(focusRoot);ids=new Set([...ids].filter(id=>keep.has(id)))}
 const hidden=new Set();
 collapsed.forEach(id=>descendants(id).forEach(child=>hidden.add(child)));
 ids=new Set([...ids].filter(id=>!hidden.has(id)));
 return ids;
}
function layout(ids){
 positions.clear();
 const incomingCount=new Map([...ids].map(id=>[id,0]));
 graph.edges.forEach(e=>{if(ids.has(e.source)&&ids.has(e.target))incomingCount.set(e.target,(incomingCount.get(e.target)||0)+1)});
 const roots=[...ids].filter(id=>nodeById.get(id)?.metadata?.entryPoint||incomingCount.get(id)===0);
 const depth=new Map(),q=roots.map(id=>[id,0]);
 while(q.length){const[id,d]=q.shift();if(depth.has(id)&&depth.get(id)<=d)continue;depth.set(id,d);for(const target of outgoing.get(id)||[]){if(ids.has(target))q.push([target,d+1])}}
 ids.forEach(id=>{if(!depth.has(id))depth.set(id,0)});
 const levels=new Map();ids.forEach(id=>{const d=depth.get(id);if(!levels.has(d))levels.set(d,[]);levels.get(d).push(id)});
 [...levels.entries()].sort((a,b)=>a[0]-b[0]).forEach(([d,nodes])=>nodes.forEach((id,i)=>positions.set(id,{x:130+d*245,y:90+i*88})));
}
function render(){
 const ids=visibleNodes();layout(ids);svg.innerHTML='';
 const viewport=el('g',{transform:`translate(${tx} ${ty}) scale(${scale})`});svg.appendChild(viewport);
 let highlight=new Set();
 if(selected){highlight.add(selected);if(directOnly){(outgoing.get(selected)||[]).forEach(x=>highlight.add(x));(incoming.get(selected)||[]).forEach(x=>highlight.add(x))}}
 graph.edges.filter(e=>ids.has(e.source)&&ids.has(e.target)).forEach(e=>{
  const a=positions.get(e.source),b=positions.get(e.target);if(!a||!b)return;
  let cls='edge';if(directOnly&&selected)cls+=(highlight.has(e.source)&&highlight.has(e.target))?' highlighted':' dimmed';
  viewport.appendChild(el('line',{x1:a.x,y1:a.y,x2:b.x,y2:b.y,class:cls}));
 });
 graph.nodes.filter(n=>ids.has(n.id)).forEach(n=>{
  const p=positions.get(n.id);let cls=`node${selected===n.id?' selected':''}`;
  if(directOnly&&selected&&!highlight.has(n.id))cls+=' dimmed';
  const g=el('g',{class:cls,transform:`translate(${p.x} ${p.y})`});
  g.appendChild(el('circle',{r:17,fill:colors[n.type]||'#475569'}));
  const label=el('text',{x:25,y:4});label.textContent=n.label.length>52?n.label.slice(0,49)+'…':n.label;g.appendChild(label);
  const children=(outgoing.get(n.id)||[]).filter(id=>enabled.has(nodeById.get(id)?.type));
  if(children.length){
   const toggle=el('g',{class:'toggle',transform:'translate(-24 0)'});
   toggle.appendChild(el('circle',{r:9,fill:collapsed.has(n.id)?'#475569':'#111827'}));
   const sign=el('text',{x:0,y:0});sign.textContent=collapsed.has(n.id)?'+':'−';toggle.appendChild(sign);
   toggle.addEventListener('click',ev=>{ev.stopPropagation();collapsed.has(n.id)?collapsed.delete(n.id):collapsed.add(n.id);render();});
   g.appendChild(toggle);
   if(collapsed.has(n.id)){const count=el('text',{x:-39,y:25,class:'hidden-count'});count.textContent=`${descendants(n.id).size} hidden`;g.appendChild(count)}
  }
  g.addEventListener('click',ev=>{ev.stopPropagation();selected=n.id;directOnly=false;showDetails(n);render()});viewport.appendChild(g);
 });
 document.getElementById('stats').textContent=`Schema ${graph.schemaVersion} · ${ids.size}/${graph.nodes.length} nodes · ${graph.edges.length} edges${focusRoot?' · focused':''}`;
}
function showDetails(n){
 const direct=graph.edges.filter(e=>e.source===n.id||e.target===n.id),entry=entryPointFor(n.id),children=(outgoing.get(n.id)||[]).length;
 document.getElementById('details').innerHTML=`<div class="kv"><strong>Type</strong><span class="badge">${escapeHtml(n.type)}</span></div><div class="kv"><strong>Label</strong>${escapeHtml(n.label)}</div><div class="kv"><strong>ID</strong>${escapeHtml(n.id)}</div><div class="kv"><strong>Direct connections</strong>${direct.length}</div><div class="kv"><strong>Descendants</strong>${descendants(n.id).size}</div>${entry?`<div class="kv"><strong>Entry point</strong>${escapeHtml(entry.label)}</div>`:''}<div class="nav-actions"><button id="focus-node">Focus branch</button><button id="direct-node">Direct only</button>${entry&&entry.id!==n.id?'<button id="entry-node">Go to entry point</button>':''}${children?`<button id="toggle-node">${collapsed.has(n.id)?'Expand':'Collapse'} branch</button>`:''}${focusRoot?'<button id="clear-focus">Clear focus</button>':''}</div><div class="kv"><strong>Metadata</strong><div class="json">${escapeHtml(JSON.stringify(n.metadata||{},null,2))}</div></div>`;
 document.getElementById('focus-node').onclick=()=>{focusRoot=n.id;directOnly=false;fit()};
 document.getElementById('direct-node').onclick=()=>{directOnly=!directOnly;render()};
 const ep=document.getElementById('entry-node');if(ep)ep.onclick=()=>{selected=entry.id;focusRoot=null;directOnly=false;showDetails(entry);render();centerOn(entry.id)};
 const tg=document.getElementById('toggle-node');if(tg)tg.onclick=()=>{collapsed.has(n.id)?collapsed.delete(n.id):collapsed.add(n.id);showDetails(n);render()};
 const cf=document.getElementById('clear-focus');if(cf)cf.onclick=()=>{focusRoot=null;directOnly=false;fit()};
}
function centerOn(id){const p=positions.get(id);if(!p)return;tx=width()/2-p.x*scale;ty=height()/2-p.y*scale;render()}
function escapeHtml(s){return String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))}
function buildFilters(){const box=document.getElementById('filters');types.forEach(type=>{const row=document.createElement('label');row.className='filter';const cb=document.createElement('input');cb.type='checkbox';cb.checked=true;cb.onchange=()=>{cb.checked?enabled.add(type):enabled.delete(type);render()};const sw=document.createElement('span');sw.className='swatch';sw.style.background=colors[type]||'#475569';row.append(cb,sw,document.createTextNode(type));box.appendChild(row)})}
function fit(){const ids=visibleNodes();layout(ids);const ps=[...positions.values()];if(!ps.length)return;const xs=ps.map(p=>p.x),ys=ps.map(p=>p.y),minX=Math.min(...xs),maxX=Math.max(...xs),minY=Math.min(...ys),maxY=Math.max(...ys);scale=Math.min((width()-100)/Math.max(1,maxX-minX+100),(height()-100)/Math.max(1,maxY-minY+100),1.3);tx=(width()-(minX+maxX)*scale)/2;ty=(height()-(minY+maxY)*scale)/2;render()}
svg.addEventListener('mousedown',e=>drag={x:e.clientX-tx,y:e.clientY-ty});window.addEventListener('mousemove',e=>{if(!drag)return;tx=e.clientX-drag.x;ty=e.clientY-drag.y;svg.classList.add('dragging');render()});window.addEventListener('mouseup',()=>{drag=null;svg.classList.remove('dragging')});
svg.addEventListener('wheel',e=>{e.preventDefault();const old=scale;scale=Math.max(.2,Math.min(3,scale*(e.deltaY<0?1.12:.89)));tx=e.offsetX-(e.offsetX-tx)*(scale/old);ty=e.offsetY-(e.offsetY-ty)*(scale/old);render()},{passive:false});
svg.addEventListener('click',()=>{selected=null;directOnly=false;document.getElementById('details').innerHTML='<div class="empty">Select a node in the graph.</div>';render()});
document.getElementById('fit').onclick=fit;document.getElementById('reset').onclick=()=>{scale=1;tx=0;ty=0;focusRoot=null;directOnly=false;collapsed.clear();render()};buildFilters();render();requestAnimationFrame(fit);
</script>
</body>
</html>
HTML;
    }
}
