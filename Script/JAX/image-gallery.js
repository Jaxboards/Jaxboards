export default function (gallery) {
  if (gallery.madeGallery) {
    return;
  }
  gallery.madeGallery = true;
  const controls = document.createElement('div');
  const next = document.createElement('a');
  const prev = document.createElement('a');
  const status = {
    index: 0,
    max: Math.max(gallery.querySelectorAll('img').length, 1),
    showNext() {
      if (this.index < this.max - 1) {
        this.index += 1;
      }
      this.update();
    },
    showPrev() {
      if (this.index > 0) {
        this.index -= 1;
      }
      this.update();
    },
    update() {
      const imgs = gallery.querySelectorAll('img');
      imgs.forEach((img, i) => {
        let container;
        if (img.madeResized) {
          container = img.parentNode;
        } else {
          container = img;
        }
        container.style.display = i !== this.index ? 'none' : 'block';
      });
    },
  };
  next.innerHTML = 'Next &raquo;';
  next.href = '#';
  next.onclick = () => {
    status.showNext();
    return false;
  };

  prev.innerHTML = 'Prev &laquo;';
  prev.href = '#';
  prev.onclick = () => {
    status.showPrev();
    return false;
  };

  status.update();
  controls.appendChild(prev);
  controls.appendChild(document.createTextNode(' '));
  controls.appendChild(next);
  gallery.appendChild(controls);
}
