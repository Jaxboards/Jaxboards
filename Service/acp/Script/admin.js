function dd_menu(e){
 e=e||window.event
 var el=e.srcElement||e.target,menu,p,s
 if(el.tagName.toLowerCase()=="a") {
  var menu=$('menu_'+el.className)
  JAX.el.addClass(el,"active")
  s=menu.style
  s.display='block'
  p=JAX.el.getCoordinates(el)
  s.top=p.y+el.clientHeight+"px"
  s.left=p.x+"px"
  el.onmouseout=function(e){
   e=e||window.event
   if(!e.relatedTarget&&e.toElement) e.relatedTarget=e.toElement
   if(e.relatedTarget!=menu&&e.relatedTarget.offsetParent!=menu) {
    JAX.el.removeClass(el,"active")
    menu.style.display='none'
   }
  }
  menu.onmouseout=function(e){
   e=e||window.event
   if(!e.relatedTarget&&e.toElement) e.relatedTarget=e.toElement;
   if(e.relatedTarget!=el&&e.relatedTarget.offsetParent!=menu&&e.relatedTarget!=menu) {
    JAX.el.removeClass(el,"active")
    menu.style.display='none'
   }
  }
 }
}

function makestuffcool(){
 var switches=$$('.switch'),x,l,s,t
 if(switches&&!switches.length) switches=[switches]
 l=switches.length
 for(x=0;x<l;x++) {
  s=switches[x]
  t=document.createElement('div')
  t.className=s.className.replace("switch","switch_converted")
  t.s=s
  s.t=t
  s.style.display='none'
  if(!s.checked) t.style.backgroundPosition='bottom'
  s.set=function(onoff){
   if(onoff===undefined) onoff=!this.checked;
   this.checked=onoff;
   this.t.style.backgroundPosition=this.checked?'top':'bottom';
   if(this.onchange) this.onchange()
  }
  t.onclick=function(){this.s.set();}
  JAX.el.insertAfter(t,s)
 }
 
 var editor=$$('.editor')
 if(editor&&!editor.length){
    editor.onkeydown=function(event){if(event.keyCode==9) {JAX.editor.setSelection(editor,'    ');return false;}}
 }
}
$(makestuffcool);

 function submitForm(a,b){
  var names=[],values=[],x,l=a.elements.length,submit
   submit=a.submitButton
  for(x=0;x<l;x++) {
   if(!a[x].name||a[x].type=="submit") continue;
   if((a[x].type=="checkbox"||a[x].type=="radio")&&!a[x].checked) continue;
   names.push(a[x].name)
   values.push(a[x].value)
  }
  if(submit) {names.push(submit.name);values.push(submit.value);}
  (new JAX.ajax).load(document.location.search,0,[names,values],1,1)
  alert("Saved. Ajax-submitted so you don't lose your place");
  return false;
 }