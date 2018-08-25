var Sound = new function() {
  this.queue = this.loadedSounds = [];
  this.ready = function() {
    Sound.isready = true;
    var q = Sound.queue;
    Sound.flashObject =
      window.soundLoader && window.soundLoader.playSound
        ? window.soundLoader
        : document.soundLoader;
    if (!Sound.flashObject || !Sound.flashObject.loadSound)
      return alert("Messed up sounds");
    if (q.length)
      for (var x = 0; x < q.length; x++)
        Sound.load(q[x][0], q[x][1], q[x][2] || false);
    Sound.queue = [];
  };
  this.load = function(title, file, autoplay) {
    if (Sound.isready) {
      Sound.loadedSounds[title] = file;
      Sound.flashObject.loadSound(title, file, autoplay || false);
    } else {
      Sound.queue.push([title, file, autoplay || false]);
    }
  };
  this.loadAndPlay = function(title, file) {
    if (Sound.loadedSounds[title] == file) Sound.play(title);
    else Sound.load(title, file, true);
  };
  this.play = function(title) {
    if (Sound.flashObject) Sound.flashObject.playSound(title);
  };

  this.setup = function() {
    var d = document.createElement("div");
    d.innerHTML =
      '<embed width="0" hidden="true" height="0" allowscriptaccess="always" quality="high" name="soundLoader" src="/Script/soundLoader.swf" pluginspage="https://get.adobe.com/flashplayer/" type="application/x-shockwave-flash" style="display:block">';
    document.body.appendChild(d);
    setTimeout(Sound.ready, 2000);
  };
}();
