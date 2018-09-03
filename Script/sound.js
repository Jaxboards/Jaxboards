class Sound {
  constructor() {
    this._soundCache = {};
  }
  load(title, file, autoplay) {
    var audio = new Audio();
    this._soundCache[title] = audio;
    audio.autoplay = !!autoplay;
    audio.src = file;
  }
  play(title) {
    this._soundCache[title].play();
  }
  loadAndPlay(title, file) {
    load(title, file, true);
  }
};

// Sound is a singleton
export default new Sound();