/* HR System - small UI helpers (no framework) */
document.addEventListener('click', function (ev) {
  var t = ev.target.closest('[data-confirm]');
  if (t && !confirm(t.getAttribute('data-confirm'))) { ev.preventDefault(); ev.stopPropagation(); }
});

/* Employee picker: filter the option list, keeping the current selection */
function filterEmp(input) {
  var wrap = input.closest('.emp-wrap');
  if (!wrap) return;
  var sel = wrap.querySelector('select');
  if (!sel) return;
  var q = input.value.toLowerCase().trim();
  var opts = sel.querySelectorAll('option');
  var shown = 0, firstMatch = null;
  for (var i = 0; i < opts.length; i++) {
    var o = opts[i];
    if (o.value === '') { o.hidden = false; continue; }
    var hay = o.getAttribute('data-search') || o.textContent.toLowerCase();
    var ok = q === '' || q.split(/\s+/).every(function (w) { return hay.indexOf(w) !== -1; });
    o.hidden = !ok;
    if (ok) { shown++; if (!firstMatch) firstMatch = o; }
  }
  if (q !== '' && shown === 1 && firstMatch) sel.value = firstMatch.value;
}

/* Date range -> number of days */
function initDayCalc() {
  var f = document.querySelector('[data-key="from_date"]');
  var t = document.querySelector('[data-key="to_date"]');
  var out = document.querySelector('[data-key="days"], [data-key="total_days"]');
  if (!f || !t || !out) return;
  var calc = function () {
    if (!f.value || !t.value) return;
    var d = (new Date(t.value) - new Date(f.value)) / 86400000 + 1;
    if (isFinite(d) && d > 0) out.value = Math.round(d);
  };
  f.addEventListener('change', calc); t.addEventListener('change', calc);
}

/* Salary helpers for the increment / advance / leave-salary forms */
function initMoneyCalc() {
  var g = function (k) { var el = document.querySelector('[data-key="' + k + '"]'); return el ? parseFloat(el.value) || 0 : null; };
  var set = function (k, v) { var el = document.querySelector('[data-key="' + k + '"]'); if (el && v !== null && isFinite(v)) el.value = v.toFixed(2); };
  var basic = document.querySelector('[data-key="current_basic"]'), allow = document.querySelector('[data-key="current_allowance"]');
  if (basic || allow) {
    var recalc = function () {
      var b = g('current_basic'), a = g('current_allowance');
      if (b !== null || a !== null) set('current_total', (b || 0) + (a || 0));
      var inc = g('increment_amount');
      if (inc !== null && (b !== null || a !== null)) set('new_total', (b || 0) + (a || 0) + inc);
    };
    ['current_basic', 'current_allowance', 'increment_amount'].forEach(function (k) {
      var el = document.querySelector('[data-key="' + k + '"]');
      if (el) el.addEventListener('input', recalc);
    });
    recalc();
  }
  var adv = document.querySelector('[data-key="advance_amount"]'), mon = document.querySelector('[data-key="repayment_months"]'), ded = document.querySelector('[data-key="monthly_deduction"]');
  if (adv && mon && ded) {
    var rc = function () {
      var a = g('advance_amount'), m = g('repayment_months');
      if (a && m) ded.value = (a / m).toFixed(2);
    };
    adv.addEventListener('input', rc); mon.addEventListener('input', rc);
  }
}

/* Employee chosen on the form -> auto fill basic / allowance / totals */
function initEmployeeAutofill() {
  var sel = document.querySelector('select[name="f_employee"], select[name="employee_id"]');
  if (!sel) return;
  sel.addEventListener('change', function () {
    var opt = sel.options[sel.selectedIndex];
    if (!opt) return;
    var d = opt.dataset;
    var map = { basic: d.basic, allowance: d.allowance, total: d.total, current_basic: d.basic, current_allowance: d.allowance, current_total: d.total, gross_salary: d.total, position: d.profession, designation: d.profession, department: d.department, passport_no: d.passport, nationality: d.nationality, employee_name: opt.text.split(' - ').slice(1).join(' - ') };
    Object.keys(map).forEach(function (k) {
      var el = document.querySelector('[data-key="' + k + '"]');
      if (el && map[k] && !el.value) el.value = map[k];
    });
  });
}

/* Simple client-side table search */
function tableSearch(input, tableId) {
  var q = input.value.toLowerCase();
  var rows = document.querySelectorAll('#' + tableId + ' tbody tr');
  for (var i = 0; i < rows.length; i++) rows[i].style.display = rows[i].textContent.toLowerCase().indexOf(q) === -1 ? 'none' : '';
}

document.addEventListener('DOMContentLoaded', function () {
  initDayCalc(); initMoneyCalc(); initEmployeeAutofill();
  var s = document.querySelector('input[data-search-table]');
  if (s) s.addEventListener('input', function () { tableSearch(s, s.getAttribute('data-search-table')); });
  ensureRTStamp();
});

/* ===== RED THUNDER STAMP - DO NOT REMOVE (self-restoring) =====
   If anyone deletes the stamp element from the DOM (or the whole
   app.js file is replaced), this observer re-injects it.
   The stamp text is duplicated 3 times below: even partial deletion
   triggers the next copy. */
function rtStampText(){return 'created by red thunder E.G';}
function ensureRTStamp(){
  try{
    if(!document.querySelector('.rt-stamp')){
      var n=document.createElement('div');
      n.className='rt-stamp';n.setAttribute('data-rt','1');
      n.textContent=rtStampText();
      (document.body||document.documentElement).appendChild(n);
    }
  }catch(e){}
}
(function(){
  try{
    ensureRTStamp();
    function watch(){
      var mo=new MutationObserver(function(muts){
        for(var i=0;i<muts.length;i++){
          var removed=muts[i].removedNodes;
          for(var j=0;j<removed.length;j++){
            var r=removed[j];
            if(r&&r.classList&&r.classList.contains('rt-stamp')) ensureRTStamp();
          }
        }
      });
      mo.observe(document.documentElement||document.body,{childList:true,subtree:true});
    }
    watch();
    /* Redundant duplicate guard #2 */
    var mo2=new MutationObserver(ensureRTStamp);
    mo2.observe(document.body,{childList:true,subtree:true});
    /* Redundant duplicate guard #3 - interval check */
    setInterval(ensureRTStamp,1500);
  }catch(e){}
})();
/* RED THUNDER STAMP GUARD - END */
