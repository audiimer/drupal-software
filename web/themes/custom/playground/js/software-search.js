(function (Drupal) {
  'use strict';

  Drupal.behaviors.softwareSearch = {
    attach: function (context, settings) {

      const input = context.querySelector('.software-search-bar__input');
      const grid = context.querySelector('.software-library__grid');
      const sidebar = context.querySelector('.software-library__sidebar');

      if (!input || !grid) return;

      // ── BUILD SIDEBAR FILTERS FROM DATA ATTRIBUTES ──
      if (sidebar && !sidebar.dataset.built) {
        sidebar.dataset.built = 'true';

        const cards = grid.querySelectorAll('.software-card');
        const filters = {
          category:  { label: 'Category',         values: new Set() },
          os:        { label: 'Operating System',  values: new Set() },
          access:    { label: 'How to Access',     values: new Set() },
        };

        // Collect all unique values from cards
        cards.forEach(function (card) {
          ['category', 'os', 'access'].forEach(function (key) {
            const raw = (card.dataset[key] || '').trim();
            if (!raw) return;
            // values may be comma-separated (multi-value fields)
            raw.split(',').forEach(function (v) {
              v = v.trim();
              if (v) filters[key].values.add(v);
            });
          });
        });

        // Render each filter group
        Object.entries(filters).forEach(function ([key, filter]) {
          if (filter.values.size === 0) return;

          const group = document.createElement('div');
          group.className = 'facet-group';
          group.innerHTML = '<h3 class="facet-group__title">' + filter.label + '</h3>';

          const items = document.createElement('div');
          items.className = 'facet-group__items';

          [...filter.values].sort().forEach(function (val) {
            const id = 'filter-' + key + '-' + val.replace(/\s+/g, '-');
            const label = document.createElement('label');
            label.htmlFor = id;

            const cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.id = id;
            cb.dataset.filterKey = key;
            cb.dataset.filterVal = val;
            cb.checked = false;

            // Capitalize display label
            const span = document.createElement('span');
            span.textContent = val.charAt(0).toUpperCase() + val.slice(1);

            label.appendChild(cb);
            label.appendChild(span);
            items.appendChild(label);
          });

          group.appendChild(items);
          sidebar.appendChild(group);
        });

        // Listen for checkbox changes
        sidebar.addEventListener('change', filterCards);
      }

      // ── FILTER LOGIC ──
      function filterCards() {
        const query = input.value.toLowerCase().trim();
        const cards = grid.querySelectorAll('.software-card');

        // Collect active filters per key
        const active = { category: [], os: [], access: [] };
        if (sidebar) {
          sidebar.querySelectorAll('input[type="checkbox"]:checked').forEach(function (cb) {
            active[cb.dataset.filterKey].push(cb.dataset.filterVal);
          });
        }

        let visibleCount = 0;

        cards.forEach(function (card) {
          const titleEl = card.querySelector('.software-card__title a');
          const titleText = titleEl ? titleEl.textContent.toLowerCase() : '';

          // Search match
          const matchesSearch = !query || titleText.includes(query);

          // Facet match — card must match ALL active filter groups (AND between groups)
          // Within a group it's OR (any selected value matches)
          const matchesFacets = Object.entries(active).every(function ([key, vals]) {
            if (vals.length === 0) return true;
            const cardVals = (card.dataset[key] || '').split(',').map(v => v.trim());
            return vals.some(v => cardVals.includes(v));
          });

          const row = card.closest('.software-grid__item') || card.closest('.views-row') || card;
          if (matchesSearch && matchesFacets) {
            row.style.display = '';
            visibleCount++;
          } else {
            row.style.display = 'none';
          }
        });

        // Empty state
        let emptyMsg = grid.querySelector('.software-no-results');
        if (visibleCount === 0) {
          if (!emptyMsg) {
            emptyMsg = document.createElement('p');
            emptyMsg.className = 'software-no-results';
            emptyMsg.textContent = Drupal.t('No software found matching your search.');
            grid.appendChild(emptyMsg);
          }
          emptyMsg.style.display = '';
        } else if (emptyMsg) {
          emptyMsg.style.display = 'none';
        }
      }

      // ── SEARCH INPUT ──
      input.addEventListener('input', filterCards);

      const btn = context.querySelector('.software-search-bar__btn');
      if (btn) {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          filterCards();
        });
      }

    }
  };

}(Drupal));