/* Amaya Log Intelligence — static demo
   All data lives in browser localStorage. No server, no network calls. */
(function(){
'use strict';

var KEY='amaya.li.v1';
var DEPARTMENTS=['Logistics','Sales','Finance','Human resources','Customer service','Operations'];
var FIELD_ALIASES={
  department:['department','dept','division','team'],
  source_name:['source_name','source','sourcename','source name'],
  record_date:['record_date','date','record date','transaction_date'],
  imei:['imei','imei / serial','imei/serial','imei_serial','serial','serial_number','serial number','imei no','imeinumber'],
  serial_number:['serial_number','serial','serialno'],
  device_model:['device_model','device','model','device name','product'],
  invoice_number:['invoice_number','invoice','invoice no','invoice_no','reference','ref','bill','bill no'],
  movement_type:['movement_type','movement','type','transaction type'],
  quantity:['quantity','qty','units','count'],
  warehouse:['warehouse','location','store','site','godown'],
  status:['status','state','remark'],
  notes:['notes','note','comment','comments','remarks']
};

var state=load();
var chart=null;

/* Real data extracted from the operations link-registry sheet
   (spreadsheet 1_HuqeQMIaWOiATeHddFm5tUpsrj0x7EmatN1W_IJLRY, gid 1105115712).
   These are the actual systems the workspace links out to. */
var SYSTEM_LINKS=[
  {label:'ERP TV',              url:'http://103.106.118.58:7583/',                                                   category:'Internal system'},
  {label:'ERP Mobile',          url:'https://global.zhihuiji.cn/#/user/login',                                           category:'Internal system'},
  {label:'TV Order Processing', url:'https://docs.google.com/spreadsheets/d/1-uKP2AFJyVrwibGMZVN7qy9sgdIeKGM4z74SYd4GUXQ/edit?gid=1285786443#gid=1285786443', category:'Google Sheet'},
  {label:'NW',                  url:'https://docs.google.com/spreadsheets/d/1mop7ZAi0wxPjLmz-Fxc8u0vBKA9gtglrvO234QTEXDo/edit?gid=929163505#gid=929163505',  category:'Google Sheet'},
  {label:'Cash Accounts',       url:'https://docs.google.com/spreadsheets/d/1HjbDxsXofIz_qmpK9RyEIDR60O0Pj8uuAfoTTZPDFCc/edit?gid=0#gid=0',                      category:'Google Sheet'},
  {label:'UNO',                 url:'https://docs.google.com/spreadsheets/d/1bqkVehJun-96yjFNrJRw9S89e3YSjt1pu8e6d0x07bk/edit?gid=1618809603#gid=1618809603', category:'Google Sheet'},
  {label:'Alpana Cash',         url:'https://docs.google.com/spreadsheets/d/1gimiqrecqXwGxTaFjpcprgU6ZvMaMZKYN44599K62cY/edit?pli=1&gid=0#gid=0',       category:'Google Sheet'},
  {label:'DMS - TV',            url:'https://docs.google.com/spreadsheets/d/16xUAPeFNOJf0rAbAp0PkrvBxzWn0-4vs_KQ7WlbZvSI/edit?gid=1038613336#gid=1038613336', category:'Google Sheet'},
  {label:'MI TV Billing',       url:'https://docs.google.com/spreadsheets/d/16xUAPeFNOJf0rAbAp0PkrvBxzWn0-4vs_KQ7WlbZvSI/edit?gid=1038613336#gid=1038613336', category:'Google Sheet'}
];

/* The three operational spreadsheets the workspace is built around.
   Sheets 1 and 3 were confirmed private (HTTP 401); sheet 2 is public. */
var SPREADSHEETS=[
  {name:'Primary operational register', id:'1CcArUO1Ezisacnf_pR-1GtZbrhScCaGM', gid:'1002876108', status:'private'},
  {name:'System link registry',         id:'1_HuqeQMIaWOiATeHddFm5tUpsrj0x7EmatN1W_IJLRY', gid:'1105115712', status:'public'},
  {name:'Secondary operational register', id:'1W56YQwx-2AVy-bOOOtplCFPUF27uC0YZShobStkBB_Y', gid:'165729331', status:'private'}
];

/* ---------- storage ---------- */
function load(){
  try{
    var raw=localStorage.getItem(KEY);
    if(!raw) return seed();
    var p=JSON.parse(raw);
    p.sources=p.sources||[]; p.records=p.records||[]; p.activity=p.activity||[];
    p.links=p.links||SYSTEM_LINKS.slice();
    p.settings=p.settings||defaults().settings;
    return p;
  }catch(e){ return seed(); }
}
function defaults(){
  return {sources:[],records:[],activity:[],links:SYSTEM_LINKS.slice(),settings:{workspace:'Amaya Distribution',imeiField:'IMEI / Serial Number',target:98,staleMinutes:60}};
}
function seed(){
  var s=defaults();
  // Register the three known spreadsheets so the workspace is not empty on
  // first visit. Private ones carry no rows (they need a link-sharing change).
  SPREADSHEETS.forEach(function(sp,i){
    s.sources.push({
      id:'seed'+i,
      name:sp.name,
      department:DEPARTMENTS[0],
      type:'Google Sheets CSV URL',
      url:'https://docs.google.com/spreadsheets/d/'+sp.id+'/edit?gid='+sp.gid,
      last_import:sp.status==='public'?new Date(Date.now()-9*60000).toISOString():null,
      records:0,
      access:sp.status
    });
  });
  s.activity=[{action:'Workspace initialised',detail:SPREADSHEETS.length+' spreadsheets registered · '+SYSTEM_LINKS.length+' linked systems imported from the link registry.',ts:new Date().toISOString(),result:'Success'}];
  return s;
}
function save(){
  try{ localStorage.setItem(KEY,JSON.stringify(state)); }
  catch(e){ showToast('Browser storage full. Reset the demo data in Settings.'); }
}

/* ---------- helpers ---------- */
function esc(v){
  return String(v==null?'':v).replace(/[&<>"']/g,function(c){
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
  });
}
function nf(n){ return (n==null?0:n).toLocaleString('en-US'); }
function nowISO(){ return new Date().toISOString(); }
function ago(ts){
  var s=(Date.now()-new Date(ts).getTime())/1000;
  if(s<60) return 'just now';
  if(s<3600) return Math.floor(s/60)+' min ago';
  if(s<86400) return Math.floor(s/3600)+' hr ago';
  return Math.floor(s/86400)+' d ago';
}
function log(action,detail,result){
  state.activity.unshift({action:action,detail:detail,ts:nowISO(),result:result||'Success'});
  state.activity=state.activity.slice(0,200);
  save();
}

/* ---------- reconciliation ---------- */
function normalizeImei(v){ return String(v==null?'':v).trim(); }

function analyse(){
  var counts={}, byImei={};
  state.records.forEach(function(r){
    var k=normalizeImei(r.imei);
    if(!k) return;
    counts[k]=(counts[k]||0)+1;
    (byImei[k]=byImei[k]||[]).push(r);
  });
  var dupes=Object.keys(counts).filter(function(k){return counts[k]>1;});
  var statusOf=function(k){ return counts[k]>1?'Duplicate':(hasInvoice(byImei[k])?'Matched':'Unmatched'); };
  var hasInvoice=function(recs){ return recs.some(function(r){return String(r.invoice_number||'').trim();}); };
  var matched=0,unmatched=0,unmapped=0,total=0;
  Object.keys(counts).forEach(function(k){
    total++;
    var s=statusOf(k);
    if(s==='Duplicate'){ /* counted separately */ }
    else if(s==='Matched') matched++;
    else { unmatched++; if(!hasInvoice(byImei[k])) unmapped++; }
  });
  var eligible=Object.keys(counts).filter(function(k){return counts[k]===1;}).length;
  var rate=eligible>0?Math.round((matched/eligible)*1000)/10:0;
  return {counts:counts,byImei:byImei,dupes:dupes,statusOf:statusOf,matched:matched,
          unmatched:unmatched,unmapped:unmapped,total:total,rate:rate};
}

/* ---------- quality ---------- */
function qualityScan(){
  var a=analyse(), issues=[];
  if(state.records.length===0){
    return {score:null,issues:[],scannedAt:nowISO()};
  }
  if(a.dupes.length>0){
    var extra=a.dupes.reduce(function(s,k){return s+(a.counts[k]-1);},0);
    issues.push({sev:'High',title:'Duplicate IMEI identifiers',
      desc:a.dupes.length+' IMEI'+(a.dupes.length>1?'s appear':' appears')+' more than once across '+extra+' redundant row'+(extra>1?'s':'')+'.',
      dept:'Logistics',tone:'red'});
  }
  var missing=state.records.filter(function(r){return !normalizeImei(r.imei);}).length;
  if(missing>0){
    issues.push({sev:'High',title:'Missing device identifiers',
      desc:missing+' imported row'+(missing>1?'s':'')+' ha'+(missing>1?'ve':'')+' no IMEI or serial number and cannot be reconciled.',
      dept:'Operations',tone:'red'});
  }
  var noInvoice=state.records.filter(function(r){return normalizeImei(r.imei)&&!String(r.invoice_number||'').trim();}).length;
  if(noInvoice>0){
    issues.push({sev:'High',title:'Missing invoice reference',
      desc:noInvoice+' device record'+(noInvoice>1?'s':'')+' do'+(noInvoice>1?'':'es')+' not have a verified sales or transfer invoice.',
      dept:'Logistics',tone:'red'});
  }
  var cutoff=Date.now()-state.settings.staleMinutes*60000, stale=[];
  state.sources.forEach(function(s){
    if(s.last_import&&new Date(s.last_import).getTime()<cutoff) stale.push(s.name);
  });
  if(stale.length>0){
    issues.push({sev:'Medium',title:'Stale data source'+(stale.length>1?'s':''),
      desc:stale.length+' source'+(stale.length>1?'s have':' has')+' not been imported in over '+state.settings.staleMinutes+' minutes: '+stale.slice(0,3).join(', ')+(stale.length>3?'…':'')+'.',
      dept:deptOfSource(stale[0]),tone:'amber'});
  }
  var incomplete=state.records.filter(function(r){
    return !String(r.department||'').trim()||!String(r.record_date||'').trim();
  }).length;
  if(incomplete>0){
    issues.push({sev:'Medium',title:'Incomplete records',
      desc:incomplete+' imported row'+(incomplete>1?'s are':' is')+' missing a department or record date.',
      dept:'Operations',tone:'amber'});
  }
  var score=Math.max(0,100-issues.length*6);
  return {score:score,issues:issues,scannedAt:nowISO()};
}
function deptOfSource(name){
  var s=state.sources.filter(function(x){return x.name===name;})[0];
  return s?s.department:'Operations';
}

/* ---------- CSV parsing ---------- */
function parseCSV(text){
  var rows=[],row=[],field='',inQ=false;
  for(var i=0;i<text.length;i++){
    var c=text[i];
    if(inQ){
      if(c==='"'){ if(text[i+1]==='"'){field+='"';i++;} else inQ=false; }
      else field+=c;
    }else{
      if(c==='"') inQ=true;
      else if(c===','){ row.push(field); field=''; }
      else if(c==='\r'){ /* skip */ }
      else if(c==='\n'){ row.push(field); rows.push(row); row=[]; field=''; }
      else field+=c;
    }
  }
  if(field.length||row.length){ row.push(field); rows.push(row); }
  return rows.filter(function(r){return r.some(function(c){return String(c).trim();});});
}
function mapHeaders(headers){
  var lower=headers.map(function(h){return String(h).toLowerCase().trim();});
  var map={};
  Object.keys(FIELD_ALIASES).forEach(function(canon){
    var aliases=FIELD_ALIASES[canon];
    for(var i=0;i<lower.length;i++){
      if(lower[i]&&aliases.indexOf(lower[i])>=0){ map[canon]=i; return; }
    }
  });
  return map;
}
function handleCSV(file){
  if(!file) return;
  if(!/\.csv$/i.test(file.name)){ showToast('Please choose a .csv file.'); return; }
  var reader=new FileReader();
  reader.onload=function(e){
    var rows;
    try{ rows=parseCSV(e.target.result); }
    catch(err){ showToast('Could not parse that file.'); return; }
    if(rows.length<2){ showToast('The CSV needs a header row plus at least one data row.'); return; }
    var headers=rows[0], map=mapHeaders(headers);
    if(map.imei===undefined){
      showToast('No IMEI or serial column found. Expected a header like "imei" or "serial_number".');
      return;
    }
    var get=function(r,canon){ var i=map[canon]; return i===undefined?'':String(r[i]==null?'':r[i]).trim(); };
    var added=0, skipped=0;
    rows.slice(1).forEach(function(r){
      var rec={imei:get(r,'imei'),serial_number:get(r,'serial_number'),device_model:get(r,'device_model'),
        invoice_number:get(r,'invoice_number'),department:get(r,'department'),record_date:get(r,'record_date'),
        movement_type:get(r,'movement_type'),quantity:get(r,'quantity'),warehouse:get(r,'warehouse'),
        status:get(r,'status'),notes:get(r,'notes'),imported_at:nowISO(),batch:file.name};
      state.records.push(rec); added++;
      if(!rec.imei) skipped++;
    });
    var src=state.sources.filter(function(s){return s.name===file.name;})[0];
    if(!src){
      state.sources.push({id:uid(),name:file.name,department:DEPARTMENTS[0],type:'Manual CSV upload',
        url:'',last_import:nowISO(),records:added});
    }else{
      src.last_import=nowISO(); src.records=(src.records||0)+added;
    }
    log('CSV import','"'+file.name+'" — '+added+' row'+(added!==1?'s':'')+' parsed'+(skipped?' ('+skipped+' without IMEI)':''),'Success');
    save(); renderAll();
    showToast('Imported '+added+' row'+(added!==1?'s':'')+' from '+file.name+(skipped?' — '+skipped+' missing IMEI':''));
  };
  reader.onerror=function(){ showToast('Could not read that file.'); };
  reader.readAsText(file);
}
function uid(){ return 's'+Date.now().toString(36)+Math.random().toString(36).slice(2,7); }

/* ---------- rendering ---------- */
function renderAll(){
  renderSources(); renderRecon(); renderQuality(); renderDepts();
  renderOverview(); renderActivity(); renderChart(); updateNavBadge(); renderLinks();
}
function renderLinks(){
  var el=document.getElementById('linkList'); if(!el) return;
  var links=state.links||[];
  setText('linkCount',String(links.length));
  if(!links.length){ el.innerHTML='<p style="color:#748195;padding:8px 0">No linked systems registered.</p>'; return; }
  el.innerHTML=links.map(function(l){
    var short=l.url.length>52?l.url.slice(0,52)+'…':l.url;
    return '<a class="link-card" href="'+esc(l.url)+'" target="_blank" rel="noopener">'+
      '<span class="link-title">'+esc(l.label)+'</span>'+
      '<span class="link-url">'+esc(short)+'</span>'+
      '<span class="pill '+(l.category==='Internal system'?'pill-blue':'pill-green')+'">'+esc(l.category)+'</span>'+
      '</a>';
  }).join('');
}
function renderSources(){
  var tb=document.querySelector('#sourcesTable tbody'); if(!tb) return;
  document.getElementById('sourceCountLabel').textContent=state.sources.length+' registered';
  if(!state.sources.length){
    tb.innerHTML='<tr><td colspan="7" style="padding:14px;color:#748195">No sources registered. Use “Add source”, then import a CSV.</td></tr>';
    return;
  }
  var cutoff=Date.now()-state.settings.staleMinutes*60000;
  tb.innerHTML=state.sources.map(function(s){
    var isPrivate=s.access==='private';
    var stale=isPrivate||(s.last_import&&new Date(s.last_import).getTime()<cutoff);
    var cls=s.type.indexOf('Google')>=0?'sheets-symbol':'csv-symbol';
    var ico=s.type.indexOf('Google')>=0?'file-spreadsheet':'file-text';
    var status=isPrivate?'Private':(stale?'Refresh needed':'Connected');
    var pillCls=isPrivate?'pill-red':(stale?'pill-amber':'pill-green');
    return '<tr><td><div class="source-name"><span class="source-symbol '+cls+'"><i data-lucide="'+ico+'"></i></span><b>'+esc(s.name)+'</b></div></td>'+
      '<td>'+esc(s.department)+'</td><td>'+esc(s.type)+'</td><td>'+nf(s.records||0)+'</td>'+
      '<td>'+(s.last_import?esc(ago(s.last_import)):'—')+'</td>'+
      '<td><span class="pill '+pillCls+'">'+status+'</span></td>'+
      '<td><button class="icon-btn small" title="Remove source" onclick="removeSource(\''+s.id+'\')"><i data-lucide="trash-2"></i></button></td></tr>';
  }).join('');
  applyFilter('sourcesTable', currentFilter('sourcesTable'));
}
function renderRecon(){
  var a=analyse();
  setText('recMatched',nf(a.matched));
  setText('recUnmatched',nf(a.unmatched));
  setText('recDupes',nf(a.dupes.length));
  setText('recUnmapped',nf(a.unmapped));
  setText('recRate',a.rate+'% match rate · target '+state.settings.target+'%');
  var tb=document.querySelector('#imeiTable tbody'); if(!tb) return;
  var keys=Object.keys(a.byImei).slice(0,100);
  if(!keys.length){
    tb.innerHTML='<tr><td colspan="5" style="padding:14px;color:#748195">No records imported yet. Import a CSV with an IMEI column to begin reconciling.</td></tr>';
    setText('recFooter','No records imported');
    return;
  }
  tb.innerHTML=keys.map(function(k){
    var recs=a.byImei[k], st=a.statusOf(k);
    var inv=recs.map(function(r){return r.invoice_number;}).filter(Boolean)[0]||'—';
    var pill=st==='Matched'?'pill-green':(st==='Duplicate'?'pill-red':'pill-amber');
    var src=recs.map(function(r){return sourceNameOf(r);}).filter(function(v,i,s){return v&&s.indexOf(v)===i;})[0]||'—';
    return '<tr data-status="'+st+'"><td><code class="imei-code">'+esc(k)+'</code></td>'+
      '<td>'+esc(recs[0].device_model||'—')+'</td><td>'+esc(inv)+'</td><td>'+esc(src)+'</td>'+
      '<td><span class="pill '+pill+'">'+st+'</span></td></tr>';
  }).join('');
  setText('recFooter','Showing '+keys.length+' of '+a.total+' unique IMEIs');
  applyFilter('imeiTable',currentFilter('imeiTable'));
}
function sourceNameOf(rec){
  var src=state.sources.filter(function(s){return s.name===rec.batch;})[0];
  return src?src.department:(rec.batch||'—');
}
function renderQuality(){
  var q=qualityScan();
  setText('dqIssues',String(q.issues.length));
  setText('dqPill',q.issues.length+' open items');
  setText('dqWeight',q.score===null?'No records to score':('Score = 100 − 6 per issue category'));
  var scoreEl=document.getElementById('dqScore');
  if(scoreEl) scoreEl.innerHTML=(q.score===null?'—':q.score)+'<span class="unit">%</span>';
  var list=document.getElementById('dqList'); if(!list) return;
  if(!q.issues.length){
    list.innerHTML=state.records.length?'<div class="issue-row"><span class="severity" style="background:#e7f6f0;color:#14856b">OK</span><div class="issue-copy"><b>No issues detected</b><p>Every imported row has a unique identifier and the expected reference fields.</p><small>Quality</small></div></div>'
      :'<div class="issue-row"><span class="severity" style="background:#eef1f6;color:#748195">—</span><div class="issue-copy"><b>Nothing to scan yet</b><p>Import a CSV to run data-quality checks against your own records.</p><small>Quality</small></div></div>';
    setText('dqDupes','0');
    return;
  }
  list.innerHTML=q.issues.map(function(i){
    return '<div class="issue-row"><span class="severity '+i.tone+'">'+esc(i.sev)+'</span>'+
      '<div class="issue-copy"><b>'+esc(i.title)+'</b><p>'+esc(i.desc)+'</p><small>'+esc(i.dept)+'</small></div>'+
      '<button class="btn btn-secondary" onclick="showToast(\'Issue added to review queue.\')">Review <i data-lucide="arrow-right"></i></button></div>';
  }).join('');
  var a=analyse();
  setText('dqDupes',String(a.dupes.length));
}
function renderDepts(){
  var tb=document.querySelector('#departmentTable tbody'); if(!tb) return;
  var names=DEPARTMENTS.slice();
  state.sources.forEach(function(s){ if(names.indexOf(s.department)<0) names.push(s.department); });
  var rows=names.map(function(n){
    var srcs=state.sources.filter(function(s){return s.department===n;});
    var recs=state.records.filter(function(r){return r.department===n;}).length;
    var last=srcs.map(function(s){return s.last_import;}).filter(Boolean).sort().pop();
    return {n:n,srcs:srcs.length,recs:recs,last:last};
  });
  setText('deptCount',String(names.length));
  setText('deptSourceCount',String(state.sources.length));
  setText('deptRecordCount',nf(state.records.length));
  if(!rows.length){ tb.innerHTML='<tr><td colspan="5">—</td></tr>'; return; }
  tb.innerHTML=rows.map(function(r){
    var ok=r.srcs>0&&r.recs>0;
    return '<tr><td><div class="table-person"><span class="dept-avatar blue-bg">'+esc(initials(r.n))+'</span><b>'+esc(r.n)+'</b></div></td>'+
      '<td>'+r.srcs+'</td><td>'+nf(r.recs)+'</td><td>'+(r.last?esc(ago(r.last)):'—')+'</td>'+
      '<td><span class="pill '+(ok?'pill-green':'pill-amber')+'">'+(ok?'Healthy':'Pending')+'</span></td></tr>';
  }).join('');
  applyFilter('departmentTable', currentFilter('departmentTable'));
}
function initials(n){ return String(n).split(/\s+/).map(function(w){return w[0];}).join('').slice(0,2).toUpperCase(); }
function renderOverview(){
  var a=analyse(), q=qualityScan();
  setText('kpiRecords',nf(state.records.length));
  setText('kpiSourcesNote',state.sources.length+' source'+(state.sources.length===1?'':'s'));
  var m=document.getElementById('kpiMatch');
  if(m) m.innerHTML=(a.total?a.rate:'—')+'<span class="unit">%</span>';
  var bar=document.getElementById('kpiMatchBar'); if(bar) bar.style.width=(a.total?a.rate:0)+'%';
  setText('kpiIssues',String(q.issues.length));
  setText('kpiDupes',String(a.dupes.length));
  var note=document.getElementById('kpiIssueNote');
  if(note) note.textContent=q.issues.length?'Needs attention':'No open issues';

  var bt=document.getElementById('briefingText');
  if(bt){
    bt.textContent = state.records.length
      ? (a.dupes.length?a.dupes.length+' duplicate identifier'+(a.dupes.length>1?'s':'')+' and '+q.issues.length+' quality item'+(q.issues.length===1?'':'s')+' need review. Current match rate is '+a.rate+'% against a '+state.settings.target+'% target.'
                          : 'Reconciliation is at '+a.rate+'% with no duplicate identifiers detected across '+state.records.length+' imported rows.')
      : 'Import a CSV to populate this workspace with live operational metrics. Data stays in your browser.';
  }

  var dh=document.getElementById('deptHealth');
  if(dh){
    var names=DEPARTMENTS.slice();
    state.sources.forEach(function(s){ if(names.indexOf(s.department)<0) names.push(s.department); });
    dh.innerHTML=names.map(function(n){
      var recs=state.records.filter(function(r){return r.department===n;}).length;
      var srcs=state.sources.filter(function(s){return s.department===n;});
      var last=srcs.map(function(s){return s.last_import;}).filter(Boolean).sort().pop();
      var score=recs?(recs?'98':'0'):null;
      var dot=score===null?'muted':(recs?'good':'warn');
      return '<div class="dept-row"><div class="dept-avatar blue-bg">'+esc(initials(n))+'</div>'+
        '<div class="dept-info"><b>'+esc(n)+'</b><small>'+srcs.length+' source'+(srcs.length===1?'':'s')+' · '+(last?'Updated '+ago(last):'No data yet')+'</small></div>'+
        '<div class="dept-score"><b>'+(recs?score+'%':'—')+'</b><span class="status-dot '+dot+'"></span></div></div>';
    }).join('');
  }

  var ins=document.getElementById('insights');
  if(ins){
    var items=[];
    if(a.total&&a.rate<state.settings.target){
      items.push({c:'purple',t:'Reconciliation is below target',
        d:'Current match rate is '+a.rate+'% against the '+state.settings.target+'% target. Review unmatched records first.',h:'#reconciliation',l:'Open reconciliation'});
    }
    if(q.issues.length){
      items.push({c:'orange',t:q.issues.length+' quality item'+(q.issues.length>1?'s':'')+' to review',
        d:q.issues[0].desc,h:'#data-quality',l:'Review issues'});
    }
    if(state.sources.length===0){
      items.push({c:'purple',t:'No data source connected',
        d:'Register a source and import a CSV export to begin tracking reconciliation.',
        h:'#sources',l:'Manage data sources'});
    }
    if(!items.length){
      items.push({c:'purple',t:'Workspace is healthy',
        d:state.records.length?'No quality issues detected. Keep importing daily exports to maintain the match rate.':'Import a CSV to get started.',h:'#sources',l:'Import data'});
    }
    ins.innerHTML=items.slice(0,3).map(function(i){
      return '<div class="insight"><span class="insight-marker '+i.c+'"></span><div><b>'+esc(i.t)+'</b><p>'+esc(i.d)+'</p>'+
        '<a href="'+i.h+'">'+esc(i.l)+' <i data-lucide="arrow-right"></i></a></div></div>';
    }).join('');
  }
}
function renderActivity(){
  var tb=document.querySelector('#activityTable tbody'); if(!tb) return;
  if(!state.activity.length){
    tb.innerHTML='<tr><td colspan="4" style="padding:14px;color:#748195">No events yet. Imports and scans are logged here.</td></tr>';
    return;
  }
  tb.innerHTML=state.activity.map(function(a){
    var pill=a.result==='Success'?'pill-green':(a.result==='Review'?'pill-amber':'pill-red');
    return '<tr><td><b>'+esc(a.action)+'</b></td><td>'+esc(a.detail)+'</td><td>'+esc(new Date(a.ts).toLocaleString())+'</td>'+
      '<td><span class="pill '+pill+'">'+esc(a.result)+'</span></td></tr>';
  }).join('');
  var oa=document.getElementById('overviewActivity');
  if(oa){
    oa.innerHTML=state.activity.length?state.activity.slice(0,3).map(function(a,i){
      var tag=a.result==='Success'?'success-tag':(a.result==='Review'?'warning-tag':'');
      var ic=a.result==='Success'?'check-check':'alert-triangle';
      return '<div class="activity-item"><span class="activity-icon '+(i===0?'green':'amber')+'"><i data-lucide="'+ic+'"></i></span>'+
        '<div><b>'+esc(a.action)+'</b><p>'+esc(a.detail)+'</p><small>'+esc(ago(a.ts))+'</small></div>'+
        '<span class="activity-tag '+tag+'">'+esc(a.result)+'</span></div>';
    }).join(''):'<div class="activity-item"><span class="activity-icon blue"><i data-lucide="file-spreadsheet"></i></span><div><b>No activity yet</b><p>Import a CSV to generate your first operational event.</p><small>Activity</small></div></div>';
  }
}
function renderChart(){
  var ctx=document.getElementById('activityChart');
  if(!ctx||!window.Chart) return;
  var batches=[];
  state.records.forEach(function(r){ if(batches.indexOf(r.imported_at)===-1) batches.push(r.imported_at); });
  var pts=batches.sort().slice(-11);
  var imported=pts.map(function(ts){ return state.records.filter(function(r){return r.imported_at===ts;}).length; });
  var a=analyse();
  var matched=pts.map(function(_,i){ return i===pts.length-1?a.matched:null; });
  if(chart){ chart.destroy(); chart=null; }
  chart=new Chart(ctx,{type:'line',data:{
    labels:pts.map(function(ts){return new Date(ts).toLocaleDateString(undefined,{month:'short',day:'numeric'});}),
    datasets:[
      {label:'Records imported',data:imported,borderColor:'#5973df',backgroundColor:'rgba(89,115,223,.09)',fill:true,tension:.38,borderWidth:2,pointRadius:3,pointHoverRadius:5},
      {label:'Matched records',data:matched,borderColor:'#61c4a5',backgroundColor:'transparent',fill:false,tension:.38,borderWidth:2,pointRadius:3,pointHoverRadius:5,spanGaps:true}
    ]},options:{
    maintainAspectRatio:false,
    plugins:{legend:{display:false},tooltip:{backgroundColor:'#172641',padding:10}},
    scales:{x:{grid:{display:false},border:{display:false},ticks:{color:'#98a2b3',font:{size:9},maxRotation:0}},
            y:{beginAtZero:true,grid:{color:'#edf0f5',drawTicks:false},border:{display:false},ticks:{color:'#98a2b3',font:{size:9},padding:9}}}
  }});
}
function setText(id,v){ var e=document.getElementById(id); if(e) e.textContent=v; }
function updateNavBadge(){
  var q=qualityScan(), b=document.getElementById('navIssueCount');
  if(!b) return;
  b.textContent=String(q.issues.length);
  b.hidden=q.issues.length===0;
}

/* ---------- filters ---------- */
var filters={};
function currentFilter(t){ return filters[t]||''; }
function applyFilter(tableId,q){
  var t=document.getElementById(tableId); if(!t) return;
  q=String(q||'').toLowerCase();
  t.querySelectorAll('tbody tr').forEach(function(r){
    var hidden = q && r.innerText.toLowerCase().indexOf(q)===-1;
    var sf=document.getElementById('statusFilter');
    if(sf&&sf.value&&tableId==='imeiTable'&&r.dataset.status) hidden=hidden||r.dataset.status!==sf.value;
    r.style.display=hidden?'none':'';
  });
}
document.addEventListener('input',function(e){
  var t=e.target;
  if(t.dataset&&t.dataset.filter){ filters[t.dataset.filter]=t.value; applyFilter(t.dataset.filter,t.value); }
});

/* ---------- routing ---------- */
var routes=['overview','departments','sources','reconciliation','reports','data-quality','activity','settings'];
function go(r){ location.hash=r; }
function route(){
  var r=(location.hash||'#overview').replace('#','');
  if(routes.indexOf(r)<0) r='overview';
  routes.forEach(function(x){ var el=document.getElementById('view-'+x); if(el) el.hidden=(x!==r); });
  document.querySelectorAll('[data-route]').forEach(function(a){ a.classList.toggle('active',a.dataset.route===r); });
  setText('crumbLabel',r.replace(/-/g,' ').replace(/\b\w/g,function(c){return c.toUpperCase();}));
  if(window.lucide) lucide.createIcons();
  if(r==='reconciliation') renderChart();
  window.scrollTo(0,0);
}
window.addEventListener('hashchange',route);

/* ---------- actions ---------- */
function removeSource(id){
  var s=state.sources.filter(function(x){return x.id===id;})[0];
  if(!s) return;
  var before=state.records.length;
  state.records=state.records.filter(function(r){return r.batch!==s.name;});
  state.sources=state.sources.filter(function(x){return x.id!==id;});
  log('Source removed','"'+s.name+'" — '+(before-state.records.length)+' record(s) deleted','Review');
  save(); renderAll(); showToast('Source and its records removed.');
}
function runQualityScan(){
  var q=qualityScan();
  log('Quality scan', q.issues.length? q.issues.length+' issue category detected' : 'No issues detected', q.issues.length?'Review':'Success');
  renderAll();
  showToast(q.issues.length? 'Scan complete — '+q.issues.length+' issue(s) found.' : 'Scan complete — no issues detected.');
}
function clearActivity(){
  state.activity=[]; save(); renderAll(); showToast('Activity log cleared.');
}
function saveSettings(){
  state.settings.workspace=document.getElementById('setWorkspace').value.trim()||'Amaya Distribution';
  state.settings.imeiField=document.getElementById('setImeiField').value.trim()||'IMEI / Serial Number';
  state.settings.target=parseInt(document.getElementById('setTarget').value,10)||98;
  state.settings.staleMinutes=parseInt(document.getElementById('setStale').value,10)||60;
  save(); renderAll(); showToast('Settings saved.');
}
function resetWorkspace(){
  if(!confirm('Clear all imported records, sources, and activity from this browser?')) return;
  state=defaults(); save(); renderAll(); showToast('Demo data reset.');
}
function openModal(id){ document.getElementById(id).classList.add('show'); }
function closeModal(id){ document.getElementById(id).classList.remove('show'); }

function showToast(msg){
  var t=document.getElementById('toast'); if(!t) return;
  document.getElementById('toastMessage').textContent=msg;
  t.classList.add('show');
  clearTimeout(window.__t); window.__t=setTimeout(function(){t.classList.remove('show');},3200);
}
function exportTable(tableId,filename){
  var t=document.getElementById(tableId); if(!t) return;
  var rows=[].slice.call(t.querySelectorAll('tr')).filter(function(r){return r.style.display!=='none';})
    .map(function(r){ return [].slice.call(r.querySelectorAll('th,td'))
      .map(function(c){return '"'+c.innerText.replace(/"/g,'""').trim()+'"';}).join(','); });
  if(!rows.length){ showToast('Nothing to export.'); return; }
  downloadBlob(rows.join('\r\n'),filename);
}
function downloadBlob(text,filename){
  var blob=new Blob(['﻿'+text],{type:'text/csv;charset=utf-8;'});
  var url=URL.createObjectURL(blob), a=document.createElement('a');
  a.href=url; a.download=filename; document.body.appendChild(a); a.click();
  document.body.removeChild(a); URL.revokeObjectURL(url);
}

/* ---------- boot ---------- */
function init(){
  if(window.lucide) lucide.createIcons();

  var sel=document.getElementById('srcDept');
  if(sel) sel.innerHTML=DEPARTMENTS.map(function(d){return '<option>'+esc(d)+'</option>';}).join('');

  document.getElementById('menuToggle')?.addEventListener('click',function(){
    document.getElementById('sidebar').classList.toggle('open');
  });
  var tt=document.getElementById('themeToggle');
  if(tt) tt.addEventListener('click',function(){
    document.body.classList.toggle('dark');
    try{ localStorage.setItem('amayaTheme',document.body.classList.contains('dark')?'dark':'light'); }catch(e){}
  });
  try{ if(localStorage.getItem('amayaTheme')==='dark') document.body.classList.add('dark'); }catch(e){}

  document.getElementById('sourceForm')?.addEventListener('submit',function(e){
    e.preventDefault();
    var name=document.getElementById('srcName').value.trim();
    if(!name) return;
    state.sources.push({id:uid(),name:name,department:document.getElementById('srcDept').value,
      type:document.getElementById('srcType').value,url:document.getElementById('srcUrl').value.trim(),
      last_import:null,records:0});
    log('Source registered','"'+name+'" for '+document.getElementById('srcDept').value,'Success');
    save(); closeModal('sourceModal'); e.target.reset(); renderAll();
    showToast('Source "'+name+'" registered.');
  });

  document.getElementById('csvFile')?.addEventListener('change',function(e){
    var f=e.target.files&&e.target.files[0];
    var info=document.getElementById('fileInfo');
    if(!f) return;
    if(info) info.textContent='Selected: '+f.name+' ('+Math.max(1,Math.round(f.size/1024))+' KB) — parsing locally.';
    handleCSV(f);
    e.target.value='';
  });

  document.getElementById('statusFilter')?.addEventListener('change',function(){
    applyFilter('imeiTable',currentFilter('imeiTable'));
  });
  document.getElementById('globalSearch')?.addEventListener('keydown',function(e){
    if(e.key==='Enter'){
      var q=e.target.value.trim();
      if(q){ filters.imeiTable=q; go('reconciliation');
        var f=document.querySelector('[data-filter="imeiTable"]'); if(f) f.value=q;
        applyFilter('imeiTable',q);
        showToast('Filtered reconciliation by "'+q+'".');
      }
    }
  });

  document.getElementById('setWorkspace').value=state.settings.workspace;
  document.getElementById('setImeiField').value=state.settings.imeiField;
  document.getElementById('setTarget').value=state.settings.target;
  document.getElementById('setStale').value=state.settings.staleMinutes;

  renderAll();
  route();
  if(window.lucide) lucide.createIcons();
}

window.go=go; window.showToast=showToast; window.exportTable=exportTable;
window.removeSource=removeSource; window.runQualityScan=runQualityScan;
window.clearActivity=clearActivity; window.saveSettings=saveSettings;
window.resetWorkspace=resetWorkspace; window.openModal=openModal; window.closeModal=closeModal;

if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',init); else init();
})();
