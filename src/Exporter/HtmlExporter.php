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
.edge{stroke:#aeb7c4;stroke-width:1.3}.node circle{stroke:#fff;stroke-width:2}.node text{font-size:11px;pointer-events:none;fill:#263244}.node.selected circle{stroke:#111827;stroke-width:4}
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
<div class="muted">Drag the canvas to pan. Use the mouse wheel to zoom. Click a node to inspect its structured metadata and highlight direct connections.</div>
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
const width=()=>svg.clientWidth||900,height=()=>svg.clientHeight||700;
let scale=1,tx=0,ty=0,drag=null,selected=null;
const positions=new Map();
function el(name,attrs={}){const x=document.createElementNS(NS,name);for(const[k,v]of Object.entries(attrs))x.setAttribute(k,v);return x}
function layout(){
 const visible=graph.nodes.filter(n=>enabled.has(n.type)); const ids=new Set(visible.map(n=>n.id));
 const incoming=new Map(visible.map(n=>[n.id,0])); graph.edges.forEach(e=>{if(ids.has(e.source)&&ids.has(e.target))incoming.set(e.target,(incoming.get(e.target)||0)+1)});
 const roots=visible.filter(n=>n.metadata?.entryPoint||incoming.get(n.id)===0); const depth=new Map(); const q=roots.map(n=>[n.id,0]);
 while(q.length){const[id,d]=q.shift();if(depth.has(id)&&depth.get(id)<=d)continue;depth.set(id,d);graph.edges.filter(e=>e.source===id&&ids.has(e.target)).forEach(e=>q.push([e.target,d+1]))}
 visible.forEach(n=>{if(!depth.has(n.id))depth.set(n.id,0)});
 const levels=new Map();visible.forEach(n=>{const d=depth.get(n.id);if(!levels.has(d))levels.set(d,[]);levels.get(d).push(n)});
 [...levels.entries()].sort((a,b)=>a[0]-b[0]).forEach(([d,nodes])=>nodes.forEach((n,i)=>positions.set(n.id,{x:130+d*230,y:90+i*85})));
}
function render(){
 svg.innerHTML='';layout();const viewport=el('g',{transform:`translate(${tx} ${ty}) scale(${scale})`});svg.appendChild(viewport);
 const visibleIds=new Set(graph.nodes.filter(n=>enabled.has(n.type)).map(n=>n.id));
 graph.edges.filter(e=>visibleIds.has(e.source)&&visibleIds.has(e.target)).forEach(e=>{const a=positions.get(e.source),b=positions.get(e.target);if(!a||!b)return;viewport.appendChild(el('line',{x1:a.x,y1:a.y,x2:b.x,y2:b.y,class:'edge'}))});
 graph.nodes.filter(n=>enabled.has(n.type)).forEach(n=>{const p=positions.get(n.id),g=el('g',{class:`node${selected===n.id?' selected':''}`,transform:`translate(${p.x} ${p.y})`});const c=el('circle',{r:17,fill:colors[n.type]||'#475569'});g.appendChild(c);const label=el('text',{x:25,y:4});label.textContent=n.label.length>52?n.label.slice(0,49)+'…':n.label;g.appendChild(label);g.addEventListener('click',ev=>{ev.stopPropagation();selected=n.id;showDetails(n);render()});viewport.appendChild(g)});
 document.getElementById('stats').textContent=`Schema ${graph.schemaVersion} · ${visibleIds.size}/${graph.nodes.length} nodes · ${graph.edges.length} edges`;
}
function showDetails(n){
 const direct=graph.edges.filter(e=>e.source===n.id||e.target===n.id);document.getElementById('details').innerHTML=`<div class="kv"><strong>Type</strong><span class="badge">${escapeHtml(n.type)}</span></div><div class="kv"><strong>Label</strong>${escapeHtml(n.label)}</div><div class="kv"><strong>ID</strong>${escapeHtml(n.id)}</div><div class="kv"><strong>Direct connections</strong>${direct.length}</div><div class="kv"><strong>Metadata</strong><div class="json">${escapeHtml(JSON.stringify(n.metadata||{},null,2))}</div></div>`;
}
function escapeHtml(s){return String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))}
function buildFilters(){const box=document.getElementById('filters');types.forEach(type=>{const row=document.createElement('label');row.className='filter';const cb=document.createElement('input');cb.type='checkbox';cb.checked=true;cb.onchange=()=>{cb.checked?enabled.add(type):enabled.delete(type);render()};const sw=document.createElement('span');sw.className='swatch';sw.style.background=colors[type]||'#475569';row.append(cb,sw,document.createTextNode(type));box.appendChild(row)})}
function fit(){layout();const ps=[...positions.values()];if(!ps.length)return;const xs=ps.map(p=>p.x),ys=ps.map(p=>p.y),minX=Math.min(...xs),maxX=Math.max(...xs),minY=Math.min(...ys),maxY=Math.max(...ys);scale=Math.min((width()-100)/Math.max(1,maxX-minX+100),(height()-100)/Math.max(1,maxY-minY+100),1.3);tx=(width()-(minX+maxX)*scale)/2;ty=(height()-(minY+maxY)*scale)/2;render()}
svg.addEventListener('mousedown',e=>drag={x:e.clientX-tx,y:e.clientY-ty});window.addEventListener('mousemove',e=>{if(!drag)return;tx=e.clientX-drag.x;ty=e.clientY-drag.y;svg.classList.add('dragging');render()});window.addEventListener('mouseup',()=>{drag=null;svg.classList.remove('dragging')});svg.addEventListener('wheel',e=>{e.preventDefault();const old=scale;scale=Math.max(.2,Math.min(3,scale*(e.deltaY<0?1.12:.89)));tx=e.offsetX-(e.offsetX-tx)*(scale/old);ty=e.offsetY-(e.offsetY-ty)*(scale/old);render()},{passive:false});svg.addEventListener('click',()=>{selected=null;document.getElementById('details').innerHTML='<div class="empty">Select a node in the graph.</div>';render()});
document.getElementById('fit').onclick=fit;document.getElementById('reset').onclick=()=>{scale=1;tx=0;ty=0;render()};buildFilters();render();requestAnimationFrame(fit);
</script>
</body>
</html>
HTML;
    }
}
