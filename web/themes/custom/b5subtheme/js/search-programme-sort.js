(function (Drupal, once) {
  Drupal.behaviors.searchProgrammeSort = {
    attach: function (context) {
      once('search-programme-sort', '.catalogue-view', context).forEach(function (wrapper) {
        const form =
          wrapper.querySelector('.catg__filters form') ||
          wrapper.querySelector('form.views-exposed-form') ||
          wrapper.querySelector('form');
        if (!form) return;

        // 🔒 Desactiva refocus/scroll en TODO el formulario expuesto:
        form.setAttribute('data-disable-refocus', 'true');
        form.querySelectorAll('input, select, textarea, button').forEach(el => {
          el.setAttribute('data-disable-refocus', 'true');
        });

        const sortBy   = form.querySelector('[name="sort_by"]');
        const sortOrder = form.querySelector('[name="sort_order"]');
        if (!sortBy || !sortOrder) return;

        // Lista blanca de opciones válidas según el <select> real:
        const allowed = Array.from(sortBy.options).map(o => o.value);

        // 🔘 Botones
        wrapper.querySelectorAll('.js-sort').forEach(function (btn) {
          btn.addEventListener('click', function (e) {
            e.preventDefault();

            const by    = btn.getAttribute('data-sort-by');
            const order = btn.getAttribute('data-sort-order') || 'ASC';

            if (!allowed.includes(by)) {
              console.warn('[SORT] valor no permitido:', by, 'permitidos:', allowed);
              return;
            }

            // Marca también los selects con el atributo (por si se recrean):
            sortBy.setAttribute('data-disable-refocus', 'true');
            sortOrder.setAttribute('data-disable-refocus', 'true');

            // Setea valores
            sortBy.value = by;
            sortOrder.value = order;

            // 🚀 Dispara autosubmit por "change" (sin clicks → no hay scroll/refocus)
            sortBy.dispatchEvent(new Event('change', { bubbles: true }));
            sortOrder.dispatchEvent(new Event('change', { bubbles: true }));
          });
        });

        // 🛡️ Extra: cuando cambie cualquier input del exposed, asegúrate del atributo.
        form.addEventListener('change', function (ev) {
          const t = ev.target;
          if (t && t.setAttribute) {
            t.setAttribute('data-disable-refocus', 'true');
          }
        }, true);
      });
    }
  };
})(Drupal, once);
