/**
 * Dynamic configurator dropdowns — used on both homepage widget and /configurator page.
 * Fetches makes/models/variants/systems via /api/configurator/options without page reloads.
 */
const Configurator = {
    init(containerId, options = {}) {
        this.container = document.getElementById(containerId);
        if (!this.container) return;

        this.makeSelect = this.container.querySelector('[data-cfg="make"]');
        this.modelSelect = this.container.querySelector('[data-cfg="model"]');
        this.variantSelect = this.container.querySelector('[data-cfg="variant"]');
        this.resultsContainer = this.container.querySelector('[data-cfg="results"]');
        this.showSystems = options.showSystems !== false;

        if (this.makeSelect) this.makeSelect.addEventListener('change', () => this.onMakeChange());
        if (this.modelSelect) this.modelSelect.addEventListener('change', () => this.onModelChange());
        if (this.variantSelect) this.variantSelect.addEventListener('change', () => this.onVariantChange());
    },

    async fetchOptions(type, params = {}) {
        const qs = new URLSearchParams({ type, ...params }).toString();
        const resp = await fetch(`/api/configurator/options?${qs}`);
        return resp.json();
    },

    populateSelect(select, items, placeholder, valueKey) {
        select.innerHTML = `<option value="">${placeholder}</option>`;
        if (Array.isArray(items)) {
            items.forEach(item => {
                const opt = document.createElement('option');
                opt.value = item;
                opt.textContent = item;
                select.appendChild(opt);
            });
        } else {
            // Object keyed by name with metadata
            Object.entries(items).forEach(([name, meta]) => {
                const opt = document.createElement('option');
                opt.value = name;
                opt.textContent = meta.year_range ? `${name} (${meta.year_range})` : name;
                select.appendChild(opt);
            });
        }
        select.disabled = false;
    },

    resetSelect(select, placeholder) {
        select.innerHTML = `<option value="">${placeholder}</option>`;
        select.disabled = true;
    },

    async onMakeChange() {
        const make = this.makeSelect.value;
        this.resetSelect(this.modelSelect, '-- Select Model --');
        this.resetSelect(this.variantSelect, '-- Select Variant --');

        if (!make) {
            this.pushUrl('/systems');
            if (this.resultsContainer) this.resultsContainer.innerHTML = this.placeholder('Find Your Exhaust System', 'Select your vehicle make above to get started.', 'fa-car');
            return;
        }

        this.pushUrl('/systems/' + encodeURIComponent(make));
        if (this.resultsContainer) this.resultsContainer.innerHTML = this.placeholder(make, 'Now select a model to continue.', 'fa-car-side');

        const data = await this.fetchOptions('models', { make });
        this.populateSelect(this.modelSelect, data.items, '-- Select Model --');
    },

    async onModelChange() {
        const make = this.makeSelect.value;
        const model = this.modelSelect.value;
        this.resetSelect(this.variantSelect, '-- Select Variant --');

        if (!model) {
            this.pushUrl('/systems/' + encodeURIComponent(make));
            if (this.resultsContainer) this.resultsContainer.innerHTML = this.placeholder(make, 'Now select a model to continue.', 'fa-car-side');
            return;
        }

        this.pushUrl('/systems/' + encodeURIComponent(make) + '/' + encodeURIComponent(model));
        if (this.resultsContainer) this.resultsContainer.innerHTML = this.placeholder(make + ' ' + model, 'Now select a variant to see available systems.', 'fa-cogs');

        const data = await this.fetchOptions('variants', { make, model });
        this.populateSelect(this.variantSelect, data.items, '-- Select Variant --');
    },

    async onVariantChange() {
        const make = this.makeSelect.value;
        const model = this.modelSelect.value;
        const variant = this.variantSelect.value;

        if (!variant || !this.showSystems) {
            this.pushUrl('/systems/' + encodeURIComponent(make) + '/' + encodeURIComponent(model));
            if (this.resultsContainer) this.resultsContainer.innerHTML = this.placeholder(make + ' ' + model, 'Now select a variant to see available systems.', 'fa-cogs');
            return;
        }

        this.pushUrl('/systems/' + encodeURIComponent(make) + '/' + encodeURIComponent(model) + '/' + this.slugify(variant));
        if (this.resultsContainer) this.resultsContainer.innerHTML = this.placeholder('Loading...', 'Fetching systems for ' + variant, 'fa-spinner fa-spin');

        const data = await this.fetchOptions('systems', { make, model, variant });
        if (this.resultsContainer) {
            this.renderSystems(data.items, make, model, variant);
        }
    },

    placeholder(title, subtitle, icon) {
        return `<div class="bg-white rounded-lg shadow-lg p-12 text-center">
            <i class="fas ${icon} text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-xl font-semibold text-gray-700 mb-2">${title}</h3>
            <p class="text-gray-500">${subtitle}</p>
        </div>`;
    },

    slugify(text) {
        let slug = text.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
        if (slug.length > 50) slug = slug.substring(0, 50).replace(/-+$/, '');
        return slug || 'system';
    },

    pushUrl(url) {
        if (window.history && window.history.pushState) {
            window.history.pushState(null, '', url);
        }
    },

    renderSystems(systems, make, model, variant) {
        if (!this.resultsContainer) return;

        if (!systems || systems.length === 0) {
            this.resultsContainer.innerHTML = `
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-search text-4xl mb-3 text-gray-300"></i>
                    <p>No systems found for this variant.</p>
                </div>`;
            return;
        }

        // Sort motorsport systems to the top
        systems.sort((a, b) => (b.motorsport ? 1 : 0) - (a.motorsport ? 1 : 0));

        const heading = `<div class="mb-4">
            <h2 class="text-2xl font-semibold text-gray-800">${make} ${model}
                <span class="text-gray-500 text-lg">— ${variant}</span>
            </h2>
            <p class="text-gray-600">${systems.length} system${systems.length !== 1 ? 's' : ''} available</p>
        </div>`;

        const cards = systems.map(sys => {
            const img = (sys.system_detail_images && sys.system_detail_images[0])
                ? sys.system_detail_images[0]
                : null;
            const imgHtml = img
                ? `<img src="${img.startsWith('http') ? img : '/images/systems/' + img}" alt="${sys.system_number}" class="w-full h-full object-contain" onerror="this.style.display='none'">`
                : '<i class="fas fa-cog text-5xl text-gray-300"></i>';

            const motorsportBadge = sys.motorsport
                ? '<span class="absolute top-2 left-2 bg-red-600 text-white px-2 py-1 rounded text-xs font-bold z-10"><i class="fas fa-flag-checkered mr-1"></i>Motorsport</span>'
                : '';

            return `<a href="/systems/${encodeURIComponent(make)}/${encodeURIComponent(model)}/${this.slugify(variant)}/${this.slugify(sys.system_name || 'system')}/${encodeURIComponent(sys.system_number)}"
                       class="block bg-white rounded-lg shadow-lg hover:shadow-xl transition-shadow border-2 border-transparent hover:border-red-500 overflow-hidden">
                <div class="h-48 bg-gray-100 flex items-center justify-center overflow-hidden relative">${motorsportBadge}${imgHtml}</div>
                <div class="p-4">
                    <h3 class="text-lg font-bold text-gray-800 mb-1">${sys.system_number}</h3>
                    ${sys.pipe_diameter ? `<p class="text-sm text-gray-600 mb-1"><i class="fas fa-ruler mr-1"></i> ${sys.pipe_diameter}</p>` : ''}
                    <p class="text-sm text-gray-500">${sys.part_numbers.length} part${sys.part_numbers.length !== 1 ? 's' : ''}</p>
                    ${sys.fitting_kit_pdf ? '<p class="text-xs text-blue-600 mt-1"><i class="fas fa-file-pdf mr-1"></i> Fitting kit available</p>' : ''}
                </div>
            </a>`;
        }).join('');

        this.resultsContainer.innerHTML = heading + `<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">${cards}</div>`;
    }
};
