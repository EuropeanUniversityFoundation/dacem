document.addEventListener('DOMContentLoaded', function () {
  function updateCarousel() {
    const screenWidth = window.innerWidth;
    const carouselInner = document.querySelector('.carousel-inner');
    const carouselItems = Array.from(carouselInner.querySelectorAll('.carousel-item .row > .col-12'));

    // Limpiar las diapositivas existentes
    carouselInner.innerHTML = '';

    if (screenWidth <= 768) {
      // Pantallas pequeñas: Una tarjeta por diapositiva
      carouselItems.forEach((item, index) => {
        const carouselItem = document.createElement('div');
        carouselItem.classList.add('carousel-item');
        if (index === 0) carouselItem.classList.add('active');

        const row = document.createElement('div');
        row.classList.add('row', 'justify-content-center');
        row.appendChild(item);

        carouselItem.appendChild(row);
        carouselInner.appendChild(carouselItem);
      });
    } else {
      // Pantallas grandes: Tres tarjetas por diapositiva
      for (let i = 0; i < carouselItems.length; i += 3) {
        const carouselItem = document.createElement('div');
        carouselItem.classList.add('carousel-item');
        if (i === 0) carouselItem.classList.add('active');

        const row = document.createElement('div');
        row.classList.add('row', 'gx-3');
        carouselItems.slice(i, i + 3).forEach(item => row.appendChild(item));

        carouselItem.appendChild(row);
        carouselInner.appendChild(carouselItem);
      }
    }
  }

  // Ejecutar al cargar y redimensionar la ventana
  updateCarousel();
  window.addEventListener('resize', updateCarousel);
});
