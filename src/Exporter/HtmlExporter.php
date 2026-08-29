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
.search{margin:14px 0}.search input{width:100%;border:1px solid #cbd3dd;border-radius:7px;padding:8px 10px;font-size:13px}.search-results{margin-top:6px;max-height:220px;overflow:auto}.search-result{display:block;width:100%;text-align:left;border:0;border-radius:5px;padding:7px;background:transparent}.search-result:hover,.search-result.active{background:#eef2f7}.search-result strong{display:block;font-size:12px}.search-result span{display:block;color:#687386;font-size:10px;margin-top:2px}.search-empty{font-size:11px;color:#687386;padding:6px}.node.search-match circle{stroke:#f59e0b;stroke-width:5}.filter{display:flex;align-items:center;gap:8px;padding:5px 0;font-size:13px}.swatch{width:10px;height:10px;border-radius:50%;display:inline-block}
main{position:relative;overflow:hidden}.canvas{width:100%;height:100%;cursor:grab}.canvas.dragging{cursor:grabbing}
.edge{stroke:#aeb7c4;stroke-width:1.35;fill:none;marker-end:url(#arrow)}.edge-label{font-size:9px;fill:#64748b;pointer-events:none;paint-order:stroke;stroke:#fff;stroke-width:3;stroke-linejoin:round}.node circle{stroke:#fff;stroke-width:2;filter:drop-shadow(0 1px 2px rgba(15,23,42,.16))}.node text{font-size:11px;pointer-events:none;fill:#263244}.node.selected circle{stroke:#111827;stroke-width:4}.node.dimmed{opacity:.18}.edge.highlighted{stroke:#334155;stroke-width:2.6}.edge.path-highlighted{stroke:#2563eb;stroke-width:4;opacity:1;marker-end:url(#arrow-path)}.edge.dimmed{opacity:.08}.edge-label.dimmed{opacity:.08}.edge-label.highlighted{font-weight:700;fill:#334155}.edge-label.path-highlighted{font-weight:800;fill:#1d4ed8}.node.path-highlighted circle{stroke:#2563eb;stroke-width:5;filter:drop-shadow(0 0 4px rgba(37,99,235,.45))}.node.path-highlighted text{font-weight:700;fill:#1d4ed8}.nav-actions button.active{background:#111827;color:#fff}.toggle{cursor:pointer}.toggle text{font-size:13px;font-weight:700;fill:#fff;text-anchor:middle;dominant-baseline:central}.nav-actions{display:flex;flex-wrap:wrap;gap:6px;margin-top:12px}.nav-actions button{font-size:11px}.hidden-count{font-size:10px;fill:#64748b}
.empty{padding:16px;color:#687386}.kv{font-size:12px;margin:8px 0}.kv strong{display:block;color:#687386;margin-bottom:2px}.json{white-space:pre-wrap;word-break:break-word;font:11px ui-monospace,SFMono-Regular,Menlo,monospace;background:#f6f8fa;padding:10px;border-radius:7px}
.preset-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:6px}.preset-grid button{font-size:11px;padding:6px}.preset-grid button.active{background:#111827;color:#fff}.compact-option{display:flex;align-items:center;gap:8px;font-size:12px;margin-top:9px}.badge{display:inline-block;padding:3px 7px;border-radius:999px;background:#eef2f7;font-size:11px}
</style>
</head>
<body>
<div class="app">
<aside>
<h1>PHPFlow</h1>
<div class="muted" id="stats"></div>
<div class="toolbar"><button id="fit">Fit graph</button><button id="reset">Reset</button></div>
<div class="search">
<input id="search" type="search" placeholder="Search route, class, table, URL…" autocomplete="off">
<div id="search-results" class="search-results"></div>
</div>
<div class="legend-title">Explore</div>
<div class="preset-grid" id="presets">
<button data-preset="all" class="active">All</button>
<button data-preset="entry">Entry points</button>
<button data-preset="database">Database</button>
<button data-preset="external_http">External HTTP</button>
<button data-preset="errors">Errors</button>
</div>
<label class="compact-option"><input id="hide-technical" type="checkbox">Hide technical nodes</label>
<div class="legend-title">Node types</div>
<div id="filters"></div>
<div class="legend-title">Navigation</div>
<div class="muted">Drag to pan and use the mouse wheel to zoom. Click a node to inspect it. Database, HTTP and Errors presets keep upstream paths visible. Search always reveals its selected result. Use +/− to collapse or expand descendants.</div>
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
const outgoing=new Map(),incoming=new Map(),incomingEdges=new Map(),outgoingEdges=new Map();
graph.nodes.forEach(n=>{outgoing.set(n.id,[]);incoming.set(n.id,[]);incomingEdges.set(n.id,[]);outgoingEdges.set(n.id,[])});
graph.edges.forEach(e=>{
 if(outgoing.has(e.source)){outgoing.get(e.source).push(e.target);outgoingEdges.get(e.source).push(e)}
 if(incoming.has(e.target)){incoming.get(e.target).push(e.source);incomingEdges.get(e.target).push(e)}
});
const width=()=>svg.clientWidth||900,height=()=>svg.clientHeight||700;
let scale=1,tx=0,ty=0,drag=null,selected=null,focusRoot=null,directOnly=false,pathOnly=false,searchQuery='',searchMatches=[],explorePreset='all',hideTechnical=false;
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
function edgeKey(e){return `${e.source}\u0000${e.target}\u0000${e.type||''}\u0000${e.label||''}\u0000${e.order??''}`}
function pathFromEntryPoint(id){
 const entry=entryPointFor(id);if(!entry)return null;
 if(entry.id===id)return {entry,nodes:new Set([id]),edgeKeys:new Set()};
 const previous=new Map(),previousEdge=new Map(),seen=new Set([entry.id]),q=[entry.id];
 while(q.length){
  const current=q.shift();
  for(const edge of outgoingEdges.get(current)||[]){
   if(seen.has(edge.target))continue;
   seen.add(edge.target);previous.set(edge.target,current);previousEdge.set(edge.target,edge);
   if(edge.target===id){
    const nodes=new Set([id]),edgeKeys=new Set();let cursor=id;
    while(cursor!==entry.id){
     const edgeToCursor=previousEdge.get(cursor);if(edgeToCursor)edgeKeys.add(edgeKey(edgeToCursor));
     cursor=previous.get(cursor);if(cursor===undefined)return null;nodes.add(cursor);
    }
    return {entry,nodes,edgeKeys};
   }
   q.push(edge.target);
  }
 }
 return null;
}
function searchableText(n){
 const edgeData=[
  ...(incomingEdges.get(n.id)||[]),
  ...(outgoingEdges.get(n.id)||[])
 ].map(e=>[e.type,e.label,JSON.stringify(e.context||{})].join(' '));
 return [n.id,n.type,n.label,n.displayLabel,JSON.stringify(n.metadata||{}),...edgeData].join(' ').toLowerCase();
}
function updateSearch(){
 const box=document.getElementById('search-results'),q=searchQuery.trim().toLowerCase();
 if(!q){searchMatches=[];box.innerHTML='';render();return}
 searchMatches=graph.nodes.filter(n=>searchableText(n).includes(q)).slice(0,50);
 if(!searchMatches.length){box.innerHTML='<div class="search-empty">No matching node.</div>';render();return}
 box.innerHTML=searchMatches.map((n,i)=>`<button class="search-result${selected===n.id?' active':''}" data-search-index="${i}"><strong>${escapeHtml(n.displayLabel||n.label)}</strong><span>${escapeHtml(n.type)} · ${escapeHtml(n.label)}</span></button>`).join('');
 box.querySelectorAll('[data-search-index]').forEach(button=>button.onclick=()=>selectSearchResult(searchMatches[Number(button.dataset.searchIndex)]));
 render();
}
function revealNode(id){
 focusRoot=null;directOnly=false;pathOnly=false;explorePreset='all';hideTechnical=false;
 document.getElementById('hide-technical').checked=false;updatePresetButtons();
 for(const parent of ancestors(id))collapsed.delete(parent);
 const n=nodeById.get(id);if(n&&!enabled.has(n.type)){enabled.add(n.type);syncFilters()}
}
function selectSearchResult(n){
 revealNode(n.id);selected=n.id;pathOnly=Boolean(entryPointFor(n.id));showDetails(n);render();centerOn(n.id);
 const buttons=document.querySelectorAll('.search-result');buttons.forEach(b=>b.classList.toggle('active',searchMatches[Number(b.dataset.searchIndex)]?.id===n.id));
}
function syncFilters(){
 document.querySelectorAll('[data-node-type]').forEach(cb=>cb.checked=enabled.has(cb.dataset.nodeType));
}
const technicalTypes=new Set(['condition','return_value','continuation','control_branch','loop','loop_control']);
function presetMatches(n){
 if(explorePreset==='entry')return Boolean(n.metadata?.entryPoint);
 if(explorePreset==='database')return n.type==='database';
 if(explorePreset==='external_http')return n.type==='http_endpoint';
 if(explorePreset==='errors'){
  if(n.type==='exception')return true;
  if(n.type==='http_response'){
   const status=Number((n.label.match(/\b([45]\d\d)\b/)||[])[1]||0);
   return status>=400;
  }
  return false;
 }
 return true;
}
function updatePresetButtons(){
 document.querySelectorAll('[data-preset]').forEach(b=>b.classList.toggle('active',b.dataset.preset===explorePreset));
}
function setPreset(name){
 explorePreset=name;focusRoot=null;directOnly=false;pathOnly=false;updatePresetButtons();render();requestAnimationFrame(fit);
}
function presetVisibleIds(){
 if(explorePreset==='all')return new Set(graph.nodes.map(n=>n.id));
 const matches=graph.nodes.filter(presetMatches);
 const ids=new Set(matches.map(n=>n.id));
 if(['database','external_http','errors'].includes(explorePreset)){
  matches.forEach(n=>ancestors(n.id).forEach(id=>ids.add(id)));
 }
 return ids;
}
function visibleNodes(){
 const presetIds=presetVisibleIds();
 let ids=new Set(graph.nodes.filter(n=>presetIds.has(n.id)&&enabled.has(n.type)&&(!hideTechnical||!technicalTypes.has(n.type))).map(n=>n.id));
 if(focusRoot&&ids.has(focusRoot)){const keep=descendants(focusRoot);keep.add(focusRoot);ids=new Set([...ids].filter(id=>keep.has(id)))}
 const hidden=new Set();
 collapsed.forEach(id=>descendants(id).forEach(child=>hidden.add(child)));
 ids=new Set([...ids].filter(id=>!hidden.has(id)));
 return ids;
}
function stableNodeCompare(a,b){
 const na=nodeById.get(a),nb=nodeById.get(b);
 const ka=`${na?.type||''}\u0000${na?.displayLabel||na?.label||''}\u0000${a}`;
 const kb=`${nb?.type||''}\u0000${nb?.displayLabel||nb?.label||''}\u0000${b}`;
 return ka.localeCompare(kb);
}
function reorderLevel(nodes,neighbors,neighborOrder){
 return [...nodes].sort((a,b)=>{
  const score=id=>{
   const linked=(neighbors.get(id)||[]).filter(x=>neighborOrder.has(x));
   if(!linked.length)return null;
   return linked.reduce((sum,x)=>sum+neighborOrder.get(x),0)/linked.length;
  };
  const sa=score(a),sb=score(b);
  if(sa===null&&sb===null)return stableNodeCompare(a,b);
  if(sa===null)return 1;if(sb===null)return -1;
  return sa===sb?stableNodeCompare(a,b):sa-sb;
 });
}
function layout(ids){
 positions.clear();
 const incomingCount=new Map([...ids].map(id=>[id,0]));
 graph.edges.forEach(e=>{if(ids.has(e.source)&&ids.has(e.target))incomingCount.set(e.target,(incomingCount.get(e.target)||0)+1)});
 let roots=[...ids].filter(id=>nodeById.get(id)?.metadata?.entryPoint||incomingCount.get(id)===0).sort(stableNodeCompare);
 if(!roots.length)roots=[...ids].sort(stableNodeCompare);
 const depth=new Map(),q=roots.map(id=>[id,0]);
 while(q.length){
  const[id,d]=q.shift();if(depth.has(id)&&depth.get(id)<=d)continue;depth.set(id,d);
  [...(outgoing.get(id)||[])].filter(target=>ids.has(target)).sort(stableNodeCompare).forEach(target=>q.push([target,d+1]));
 }
 ids.forEach(id=>{if(!depth.has(id))depth.set(id,0)});
 const levels=new Map();ids.forEach(id=>{const d=depth.get(id);if(!levels.has(d))levels.set(d,[]);levels.get(d).push(id)});
 levels.forEach(nodes=>nodes.sort(stableNodeCompare));
 const levelKeys=[...levels.keys()].sort((a,b)=>a-b);
 for(let sweep=0;sweep<4;sweep++){
  for(let i=1;i<levelKeys.length;i++){
   const prev=levels.get(levelKeys[i-1]),order=new Map(prev.map((id,index)=>[id,index]));
   levels.set(levelKeys[i],reorderLevel(levels.get(levelKeys[i]),incoming,order));
  }
  for(let i=levelKeys.length-2;i>=0;i--){
   const next=levels.get(levelKeys[i+1]),order=new Map(next.map((id,index)=>[id,index]));
   levels.set(levelKeys[i],reorderLevel(levels.get(levelKeys[i]),outgoing,order));
  }
 }
 const maxCount=Math.max(1,...levelKeys.map(d=>levels.get(d).length)),rowGap=maxCount>18?68:maxCount>10?76:88;
 levelKeys.forEach(d=>{
  const nodes=levels.get(d),offset=(maxCount-nodes.length)*rowGap/2;
  nodes.forEach((id,i)=>positions.set(id,{x:130+d*255,y:90+offset+i*rowGap}));
 });
}
function render(){
 const ids=visibleNodes();layout(ids);svg.innerHTML='';
 const defs=el('defs'),marker=el('marker',{id:'arrow',viewBox:'0 0 10 10',refX:'9',refY:'5',markerWidth:'6',markerHeight:'6',orient:'auto-start-reverse'});
 marker.appendChild(el('path',{d:'M 0 0 L 10 5 L 0 10 z',fill:'#aeb7c4'}));defs.appendChild(marker);
 const pathMarker=el('marker',{id:'arrow-path',viewBox:'0 0 10 10',refX:'9',refY:'5',markerWidth:'7',markerHeight:'7',orient:'auto-start-reverse'});
 pathMarker.appendChild(el('path',{d:'M 0 0 L 10 5 L 0 10 z',fill:'#2563eb'}));defs.appendChild(pathMarker);svg.appendChild(defs);
 const viewport=el('g',{transform:`translate(${tx} ${ty}) scale(${scale})`});svg.appendChild(viewport);
 let highlight=new Set(),selectedPath=selected&&pathOnly?pathFromEntryPoint(selected):null;
 if(selected){highlight.add(selected);if(directOnly){(outgoing.get(selected)||[]).forEach(x=>highlight.add(x));(incoming.get(selected)||[]).forEach(x=>highlight.add(x))}}
 graph.edges.filter(e=>ids.has(e.source)&&ids.has(e.target)).forEach(e=>{
  const a=positions.get(e.source),b=positions.get(e.target);if(!a||!b)return;
  let cls='edge';
  if(pathOnly&&selectedPath)cls+=selectedPath.edgeKeys.has(edgeKey(e))?' path-highlighted':' dimmed';
  else if(directOnly&&selected)cls+=(highlight.has(e.source)&&highlight.has(e.target))?' highlighted':' dimmed';
  const startX=a.x+18,endX=b.x-18,midX=(startX+endX)/2;
  viewport.appendChild(el('path',{d:`M ${startX} ${a.y} C ${midX} ${a.y}, ${midX} ${b.y}, ${endX} ${b.y}`,class:cls}));
  const edgeText=e.label||e.type;
  if(edgeText){
   const label=el('text',{x:midX,y:(a.y+b.y)/2-6,class:`edge-label${cls.includes(' highlighted')?' highlighted':''}${cls.includes(' path-highlighted')?' path-highlighted':''}${cls.includes(' dimmed')?' dimmed':''}`});
   label.textContent=edgeText.length>28?edgeText.slice(0,25)+'…':edgeText;viewport.appendChild(label);
  }
 });
 graph.nodes.filter(n=>ids.has(n.id)).forEach(n=>{
  const p=positions.get(n.id);let cls=`node${selected===n.id?' selected':''}`;
  if(searchQuery.trim()&&searchableText(n).includes(searchQuery.trim().toLowerCase()))cls+=' search-match';
  if(pathOnly&&selectedPath)cls+=selectedPath.nodes.has(n.id)?' path-highlighted':' dimmed';
  else if(directOnly&&selected&&!highlight.has(n.id))cls+=' dimmed';
  const g=el('g',{class:cls,transform:`translate(${p.x} ${p.y})`});
  g.appendChild(el('circle',{r:17,fill:colors[n.type]||'#475569'}));
  const label=el('text',{x:25,y:4});const shown=n.displayLabel||n.label;label.textContent=shown.length>52?shown.slice(0,49)+'…':shown;g.appendChild(label);
  const children=(outgoing.get(n.id)||[]).filter(id=>enabled.has(nodeById.get(id)?.type));
  if(children.length){
   const toggle=el('g',{class:'toggle',transform:'translate(-24 0)'});
   toggle.appendChild(el('circle',{r:9,fill:collapsed.has(n.id)?'#475569':'#111827'}));
   const sign=el('text',{x:0,y:0});sign.textContent=collapsed.has(n.id)?'+':'−';toggle.appendChild(sign);
   toggle.addEventListener('click',ev=>{ev.stopPropagation();collapsed.has(n.id)?collapsed.delete(n.id):collapsed.add(n.id);render();});
   g.appendChild(toggle);
   if(collapsed.has(n.id)){const count=el('text',{x:-39,y:25,class:'hidden-count'});count.textContent=`${descendants(n.id).size} hidden`;g.appendChild(count)}
  }
  g.addEventListener('click',ev=>{ev.stopPropagation();selected=n.id;directOnly=false;pathOnly=Boolean(entryPointFor(n.id));showDetails(n);render()});viewport.appendChild(g);
 });
 document.getElementById('stats').textContent=`Schema ${graph.schemaVersion} · ${ids.size}/${graph.nodes.length} nodes · ${graph.edges.length} edges${focusRoot?' · focused':''}${pathOnly?' · entry path':''}${explorePreset!=='all'?` · ${explorePreset.replace('_',' ')}`:''}${hideTechnical?' · technical hidden':''}`;
}
function showDetails(n){
 const direct=graph.edges.filter(e=>e.source===n.id||e.target===n.id),entry=entryPointFor(n.id),children=(outgoing.get(n.id)||[]).length;
 const ref=n.metadata?.callable||n.metadata?.message||n.metadata?.exception||null;
 const sourceDetails=ref?`<div class="kv"><strong>Class</strong>${escapeHtml(ref.shortName||'')}</div><div class="kv"><strong>Namespace</strong>${escapeHtml(ref.namespace||'—')}</div>${ref.method!==undefined?`<div class="kv"><strong>Method</strong>${escapeHtml(ref.method||'—')}</div>`:''}<div class="kv"><strong>FQCN</strong>${escapeHtml(ref.class||'')}</div><div class="kv"><strong>File</strong>${escapeHtml(ref.file||'Unknown')}</div>`:'';
 document.getElementById('details').innerHTML=`<div class="kv"><strong>Type</strong><span class="badge">${escapeHtml(n.type)}</span></div><div class="kv"><strong>Display label</strong>${escapeHtml(n.displayLabel||n.label)}</div>${sourceDetails}<div class="kv"><strong>Canonical label</strong>${escapeHtml(n.label)}</div><div class="kv"><strong>ID</strong>${escapeHtml(n.id)}</div><div class="kv"><strong>Direct connections</strong>${direct.length}</div><div class="kv"><strong>Descendants</strong>${descendants(n.id).size}</div>${entry?`<div class="kv"><strong>Entry point</strong>${escapeHtml(entry.displayLabel||entry.label)}</div>`:''}<div class="nav-actions"><button id="focus-node">Focus branch</button><button id="direct-node"${directOnly?' class="active"':''}>Direct only</button>${entry?`<button id="path-node"${pathOnly?' class="active"':''}>Entry path</button>`:''}${entry&&entry.id!==n.id?'<button id="entry-node">Go to entry point</button>':''}${children?`<button id="toggle-node">${collapsed.has(n.id)?'Expand':'Collapse'} branch</button>`:''}${focusRoot?'<button id="clear-focus">Clear focus</button>':''}</div><div class="kv"><strong>Metadata</strong><div class="json">${escapeHtml(JSON.stringify(n.metadata||{},null,2))}</div></div>`;
 document.getElementById('focus-node').onclick=()=>{focusRoot=n.id;directOnly=false;pathOnly=false;fit()};
 document.getElementById('direct-node').onclick=()=>{directOnly=!directOnly;pathOnly=false;showDetails(n);render()};
 const pn=document.getElementById('path-node');if(pn)pn.onclick=()=>{
 pathOnly=!pathOnly;directOnly=false;
 if(pathOnly){
  for(const parent of ancestors(n.id))collapsed.delete(parent);
  const path=pathFromEntryPoint(n.id);
  if(path){path.nodes.forEach(id=>{const node=nodeById.get(id);if(node)enabled.add(node.type)});syncFilters()}
 }
 showDetails(n);render();
};
 const ep=document.getElementById('entry-node');if(ep)ep.onclick=()=>{selected=entry.id;focusRoot=null;directOnly=false;pathOnly=false;showDetails(entry);render();centerOn(entry.id)};
 const tg=document.getElementById('toggle-node');if(tg)tg.onclick=()=>{collapsed.has(n.id)?collapsed.delete(n.id):collapsed.add(n.id);showDetails(n);render()};
 const cf=document.getElementById('clear-focus');if(cf)cf.onclick=()=>{focusRoot=null;directOnly=false;pathOnly=false;fit()};
}
function centerOn(id){const p=positions.get(id);if(!p)return;tx=width()/2-p.x*scale;ty=height()/2-p.y*scale;render()}
function escapeHtml(s){return String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))}
function buildFilters(){const box=document.getElementById('filters');types.forEach(type=>{const row=document.createElement('label');row.className='filter';const cb=document.createElement('input');cb.type='checkbox';cb.checked=true;cb.dataset.nodeType=type;cb.onchange=()=>{cb.checked?enabled.add(type):enabled.delete(type);render()};const sw=document.createElement('span');sw.className='swatch';sw.style.background=colors[type]||'#475569';row.append(cb,sw,document.createTextNode(type));box.appendChild(row)})}
function fit(){const ids=visibleNodes();layout(ids);const ps=[...positions.values()];if(!ps.length)return;const xs=ps.map(p=>p.x),ys=ps.map(p=>p.y),minX=Math.min(...xs),maxX=Math.max(...xs),minY=Math.min(...ys),maxY=Math.max(...ys);scale=Math.min((width()-100)/Math.max(1,maxX-minX+100),(height()-100)/Math.max(1,maxY-minY+100),1.3);tx=(width()-(minX+maxX)*scale)/2;ty=(height()-(minY+maxY)*scale)/2;render()}
svg.addEventListener('mousedown',e=>drag={x:e.clientX-tx,y:e.clientY-ty});window.addEventListener('mousemove',e=>{if(!drag)return;tx=e.clientX-drag.x;ty=e.clientY-drag.y;svg.classList.add('dragging');render()});window.addEventListener('mouseup',()=>{drag=null;svg.classList.remove('dragging')});
svg.addEventListener('wheel',e=>{e.preventDefault();const old=scale;scale=Math.max(.2,Math.min(3,scale*(e.deltaY<0?1.12:.89)));tx=e.offsetX-(e.offsetX-tx)*(scale/old);ty=e.offsetY-(e.offsetY-ty)*(scale/old);render()},{passive:false});
svg.addEventListener('click',()=>{selected=null;directOnly=false;pathOnly=false;document.getElementById('details').innerHTML='<div class="empty">Select a node in the graph.</div>';render()});
const searchInput=document.getElementById('search');
searchInput.addEventListener('input',()=>{searchQuery=searchInput.value;updateSearch()});
searchInput.addEventListener('keydown',e=>{if(e.key==='Enter'&&searchMatches.length){e.preventDefault();selectSearchResult(searchMatches[0])}if(e.key==='Escape'){searchInput.value='';searchQuery='';searchMatches=[];document.getElementById('search-results').innerHTML='';render()}});
document.querySelectorAll('[data-preset]').forEach(button=>button.onclick=()=>setPreset(button.dataset.preset));
document.getElementById('hide-technical').onchange=e=>{hideTechnical=e.target.checked;render();requestAnimationFrame(fit)};
document.getElementById('fit').onclick=fit;document.getElementById('reset').onclick=()=>{scale=1;tx=0;ty=0;focusRoot=null;directOnly=false;pathOnly=false;explorePreset='all';hideTechnical=false;document.getElementById('hide-technical').checked=false;collapsed.clear();enabled.clear();types.forEach(type=>enabled.add(type));syncFilters();updatePresetButtons();render()};buildFilters();render();requestAnimationFrame(fit);
</script>
</body>
</html>
HTML;
    }
}
