export default class ajax {
  constructor(s) {
    this.xmlobj = window.XMLHttpRequest
    this.setup = {
      readyState: 4,
      callback: function() {},
      method: "POST",
      ...s
    };
  }

  load(a, b, c, d, e) {
    // a=URL b=callback c=send_data d=POST e=type(1=update,2=load new)
    d = d || this.setup.method || "GET";
    if (d) d = "POST";
    if (
      c &&
      Array.isArray(c) &&
      Array.isArray(c[0]) &&
      c[0].length == c[1].length
    ) {
      c = this.build_query(c[0], c[1]);
    } else if (typeof c != "string") c = this.build_query(c);
    var xmlobj = new this.xmlobj();
    if (b) this.setup.callback = b;
    xmlobj.onreadystatechange = function(status) {
      if (xmlobj.readyState == this.setup.readyState) {
        this.setup.callback(xmlobj);
      }
    };
    if (!xmlobj) return false;
    xmlobj.open(d, a, true);
    xmlobj.url = a;
    xmlobj.type = e;
    if (d) {
      xmlobj.setRequestHeader(
        "Content-Type",
        "application/x-www-form-urlencoded"
      );
    }
    xmlobj.setRequestHeader("X-JSACCESS", e || 1);
    xmlobj.send(c || null);
    return xmlobj;
  }

  build_query(a, b) {
    var q = "";
    if (b) {
      for (x = 0; x < a.length; x++) {
        q +=
          encodeURIComponent(a[x]) + "=" + encodeURIComponent(b[x] || "") + "&";
      }
    } else {
      for (x in a) {
        q += encodeURIComponent(x) + "=" + encodeURIComponent(a[x] || "") + "&";
      }
    }
    return q.substring(0, q.length - 1);
  }
};