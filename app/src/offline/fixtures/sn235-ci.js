/* GENERAT de scripts/extrage-ci.mjs din reference/v2/sn235.php (modulul CI, copiat neschimbat).
   Nu se editează de mână: rulează din nou scriptul dacă sn235 se schimbă. */
/* eslint-disable */
export function creeazaCI(ls) {
var CI={
 F:{iarna:{nume:'Iarna interioară',faza:'Faza menstruală',tema:'Odihnă, eliberare și regenerare',c:'#7A5A75'},
    primavara:{nume:'Primăvara interioară',faza:'Faza foliculară',tema:'Hrănirea resurselor care susțin creșterea',c:'#56704D'},
    vara:{nume:'Vara interioară',faza:'Faza ovulatorie',tema:'Cum ne păstrăm Yin-ul în perioadele de expansiune Yang',c:'#9E533A'},
    toamna:{nume:'Toamna interioară',faza:'Faza luteală',tema:'Întoarcerea către interior și conservarea energiei',c:'#85622E'}},
 ORD:['iarna','primavara','vara','toamna'],
 d:function(){var x=ls('ahsp_ciclu'),v=ls('ah_ciclu_v1')||{},st=[];if(x&&x.starturi)st=st.concat(x.starturi);if(v.starturi)st=st.concat(v.starturi);if(v.s)st.push(v.s);st=st.filter(function(q,i){return /^\d{4}-\d{2}-\d{2}$/.test(q)&&st.indexOf(q)===i;}).sort().reverse().slice(0,13);if(!st.length)return null;var cl=function(n,a,b,d){n=+n;return n>=a&&n<=b?n:d;};return {starturi:st,lung:cl(x&&x.lung||v.c,21,40,28),men:cl(x&&x.men||v.m,2,9,5),sit:x&&x.sit};},
 zi0:function(s){var p=s.split('-');return Date.UTC(+p[0],p[1]-1,+p[2])/864e5;},
 azi0:function(){var n=new Date();return Date.UTC(n.getFullYear(),n.getMonth(),n.getDate())/864e5;},
 iso:function(z){return new Date(z*864e5).toISOString().slice(0,10);},
 data:function(z){var L=['ianuarie','februarie','martie','aprilie','mai','iunie','iulie','august','septembrie','octombrie','noiembrie','decembrie'],d=new Date(z*864e5);return d.getUTCDate()+' '+L[d.getUTCMonth()];},
 lung:function(x){var st=x.starturi.map(CI.zi0),dif=[];for(var i=0;i+1<st.length&&dif.length<6;i++){var v=st[i]-st[i+1];if(v>=21&&v<=40)dif.push(v);}return dif.length>=2?Math.round(dif.reduce(function(a,b){return a+b;},0)/dif.length):(x.lung||28);},
 faze:function(L,m){var oS=Math.max(L-16,m+2),oE=Math.min(oS+4,L-2);return [{f:'iarna',a:1,b:m},{f:'primavara',a:m+1,b:oS-1},{f:'vara',a:oS,b:oE},{f:'toamna',a:oE+1,b:L}];},
 SIT:{natural:{t:'Ciclu natural',d:'Fără contracepție hormonală.'},
  hormonal:{t:'Contracepție hormonală',d:'Pastile, inel, plasture, implant, sterilet hormonal.',txt:'Cu contracepția hormonală nu ai ovulație, așa că anotimpurile și curbele hormonilor nu se aplică corpului tău. Poți nota în continuare sângerarea și cum te simți.'},
  sarcina:{t:'Sunt însărcinată',d:'Oprim estimările.',txt:'Cât timp ești însărcinată nu calculăm fazele. Poți nota în continuare cum te simți, iar când ciclul revine, alegi din nou „Ciclu natural”.'},
  alaptare:{t:'Alăptez',d:'Ciclul poate lipsi sau fi neregulat.',nota:'Alăptezi: ciclul poate lipsi sau fi neregulat, așa că estimările sunt mai puțin sigure.'},
  perimenopauza:{t:'Perimenopauză',d:'Ciclurile se pot schimba de la o lună la alta.',nota:'Perimenopauză: ciclurile se pot lungi sau scurta, așa că estimările sunt mai puțin sigure.'}},
 sit:function(){var x=ls('ahsp_ciclu');return x&&x.sit&&CI.SIT[x.sit]?x.sit:'natural';},
 azi:function(){var x=CI.d();if(!x)return null;var L=CI.lung(x),m=x.men||5,s0=CI.zi0(x.starturi[0]),zi=CI.azi0()-s0+1;if(zi<1)return null;var fz=CI.faze(L,m),f='toamna';fz.forEach(function(p){if(zi>=p.a&&zi<=p.b)f=p.f;});return {zi:zi,L:L,m:m,f:f,s0:s0,fz:fz,urm:s0+L,peste:zi>L};}
};
return CI;
}
