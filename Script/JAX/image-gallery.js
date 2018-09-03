export default function (gallery) {
  if (gallery.madeGallery) return;
  gallery.madeGallery = true;
  const controls = document.createElement('div');
  const next = document.createElement('a');
  const prev = document.createElement('a');
  const status = {
    index: 0,
    max: Math.max(gallery.querySelectorAll('img').length, 1),
    showNext() {
      if (this.index < this.max - 1) this.index++;
      this.update();
    },
    showPrev() {
      if (this.index > 0) this.index--;
      this.update();
    },
    update() {
      const imgs = gallery.querySelectorAll('img');
      let x;
      let img;
      for (x = 0; x < imgs.length; x++) {
        img = imgs[x];
        if (img.madeResized) {
          img = img.parentNode;
        }
        img.style.display = x != this.index ? 'none' : 'block';
      }
    },
  };
  next.innerHTML = 'Next &raquo;';
  next.href = '#';
  next.onclick = function () {
    status.showNext();
    return false;
  };

  prev.innerHTML = 'Prev &laquo;';
  prev.href = '#';
  prev.onclick = function () {
    status.showPrev();
    return false;
  };

  status.update();
  controls.appendChild(prev);
  controls.appendChild(document.createTextNode(' '));
  controls.appendChild(next);
  gallery.appendChild(controls);
}
