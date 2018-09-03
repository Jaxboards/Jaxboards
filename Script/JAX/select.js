export default function(a) {
  var r;
  if (document.selection) {
    r = document.body.createTextRange();
    r.moveToElementText(a);
    r.select();
  } else if (window.getSelection) {
    r = document.createRange();
    r.selectNode(a);
    window.getSelection().addRange(r);
  }
}