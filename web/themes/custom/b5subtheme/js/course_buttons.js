document.addEventListener('DOMContentLoaded', function () {
    // Selecciona todos los botones de toggle
    const toggleButtons = document.querySelectorAll('.toggle-button');
  
    toggleButtons.forEach(function (button) {
      button.addEventListener('click', function () {
        const targetId = button.getAttribute('data-bs-target');
        const targetElement = document.querySelector(targetId);
  
        // Cambia las clases inmediatamente al hacer clic
        if (button.id === 'structure-btn') {
          if (button.classList.contains('btn-black')) {
            button.classList.remove('btn-black');
            button.classList.add('btn-outline-secondary');
          } else {
            button.classList.remove('btn-outline-secondary');
            button.classList.add('btn-black');
          }
        } else {
          if (button.classList.contains('btn-primary')) {
            button.classList.remove('btn-primary');
            button.classList.add('btn-outline-primary');
          } else {
            button.classList.remove('btn-outline-primary');
            button.classList.add('btn-primary');
          }
        }
      });
    });
  
    // Asegúrate de que los botones iniciales marcados mantengan su estado correcto
    toggleButtons.forEach(function (button) {
      const targetId = button.getAttribute('data-bs-target');
      const targetElement = document.querySelector(targetId);
      if (targetElement.classList.contains('show')) {
        if (button.id === 'structure-btn') {
          button.classList.remove('btn-outline-secondary');
          button.classList.add('btn-black');
        } else {
          button.classList.remove('btn-outline-primary');
          button.classList.add('btn-primary');
        }
      }
    });
  });
  