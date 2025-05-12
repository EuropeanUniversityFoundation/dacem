document.addEventListener('DOMContentLoaded', function () {
  console.log('Libreri crgd');
    const sections = document.querySelectorAll('.rs-section');
    const navLinks = document.querySelectorAll('.rs-nav .nav-link'); // enlaces del menú principal de RS
    const dropdownItems = document.querySelectorAll('.dropdown-item'); // enlaces del dropdown
    const dropdownButton = document.getElementById('rsDropdownButton'); // botón del menú colapsado
  
    function updateDropdownButton(activeText) {
      if (window.innerWidth <= 992) {
        dropdownButton.textContent = activeText;
      }
    }
  
    navLinks.forEach(link => {
      link.addEventListener('click', function () {
        navLinks.forEach(nav => nav.classList.remove('active'));
        this.classList.add('active');
        updateDropdownButton(this.textContent);
      });
    });
  
    dropdownItems.forEach(item => {
      item.addEventListener('click', function () {
        dropdownItems.forEach(i => i.classList.remove('active'));
        this.classList.add('active');
        updateDropdownButton(this.textContent);
      });
    });
  
    const sectionObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const id = entry.target.getAttribute('id');
          navLinks.forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href').substring(1) === id) {
              link.classList.add('active');
              updateDropdownButton(link.textContent);
            }
          });
        }
      });
    }, {
      rootMargin: '-100px 0px -50% 0px',
      threshold: [0.1, 0.5, 0.9]
    });
  
    sections.forEach(section => {
      sectionObserver.observe(section);
    });
  
    window.addEventListener('resize', function () {
      const activeLink = document.querySelector('.rs-nav .nav-link.active') || document.querySelector('.rs-dropdown .dropdown-item.active');
      if (activeLink) {
        updateDropdownButton(activeLink.textContent);
      }
    });
  });


  document.addEventListener('DOMContentLoaded', function () {
    const collapseElement = document.getElementById('campusCollapse');
  
    collapseElement.addEventListener('show.bs.collapse', function () {
      const scrollY = window.scrollY;
      setTimeout(() => {
        window.scrollTo({ top: scrollY });
      }, 10); // Tiempo suficiente para evitar salto visual
    });
  });
  