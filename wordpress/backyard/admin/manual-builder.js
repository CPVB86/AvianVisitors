/* Builder resolves valid combinations locally; it never requests observation data. */
function backyardBuilderResolve(input, config) {
    const a = {...input};
    if (!['birds', 'bats'].includes(a.module)) a.module = 'birds';
    if (!['last', 'most', 'first', 'rarest', 'random', 'species', 'stats'].includes(a.type)) a.type = 'last';
    if (a.module !== 'birds' || a.identity !== 'otje') a.identity = '';
    const fields = a.type === 'stats' ? config.statsFields : config.speciesFields;
    if (!fields.includes(a.field)) a.field = a.type === 'stats' ? (a.field === 'last_seen' ? 'last_activity' : 'total_observations') : (a.field === 'last_activity' ? 'last_seen' : 'name');
    const image = ['perched', 'flying'].includes(a.field);
    const link = ['wikipedia_url', 'observations_url'].includes(a.field);
    const outputs = image ? ['image', 'url', 'link'] : link ? ['url', 'link'] : ['text'];
    if (!outputs.includes(a.output)) a.output = outputs[0];
    const date = /^(first|last)_(seen|date|time)$/.test(a.field) || a.field === 'last_activity';
    const species = a.type === 'species' && !a.identity;
    const rank = !a.identity && !['species', 'stats'].includes(a.type);
    if (!species) a.species = '';
    if (!date) a.format = '';
    if (!rank || !/^(?:[1-9]|10)$/.test(a.rank)) a.rank = '1';
    if (!['today','24h','7d','30d','all'].includes(a.period)) a.period = 'all';
    const today = ['today_observations', 'today_species'].includes(a.field);
    if (today) a.period = 'today';
    let error = '';
    if (species && !a.species.trim()) error = 'Vul een wetenschappelijke soortnaam in.';
    if ([a.species, a.format, a.fallback].some(value => /["\[\]\r\n]/.test(value))) error = 'Gebruik geen dubbele aanhalingstekens, blokhaken of regeleinden in de tekstvelden.';
    const attributes = {module:a.module, type:a.type, field:a.field, period:a.period};
    if (a.identity) attributes.identity = a.identity;
    if (species && a.species.trim()) attributes.species = a.species.trim();
    if (rank && a.rank !== '1') attributes.rank = a.rank;
    if (a.output !== 'text') attributes.output = a.output;
    if (date && a.format) attributes.format = a.format;
    if (a.fallback) attributes.fallback = a.fallback;
    return {a, fields, outputs, date, species, rank, today, error,
        code: error ? '' : '[backyard_data ' + Object.entries(attributes).map(([key,value]) => `${key}="${value}"`).join(' ') + ']'};
}

const backyardBuilder = document.querySelector('#backyard-builder');
if (backyardBuilder) {
    const config = JSON.parse(backyardBuilder.dataset.config);
    const controls = Object.fromEntries([...backyardBuilder.querySelectorAll('[name]')].map(el => [el.name, el]));
    const labels = {name:'Naam', scientific_name:'Wetenschappelijke naam', perched:'Afbeelding zittend', flying:'Afbeelding vliegend', first_seen:'Eerste waarneming: datum en tijd', last_seen:'Laatste waarneming: datum en tijd', last_activity:'Laatste activiteit: datum en tijd', total_observations:'Totaal waarnemingen', unique_species:'Aantal soorten', today_observations:'Waarnemingen vandaag', today_species:'Soorten vandaag', new_species:'Nieuwe soorten', active_days:'Actieve dagen', count:'Aantal waarnemingen', first_date:'Eerste datum', first_time:'Eerste tijdstip', last_date:'Laatste datum', last_time:'Laatste tijdstip', species_id:'Soortidentificatie', wikipedia_url:'Wikipedia-link', observations_url:'Waarnemingen-link'};
    function options(control, values, selected) {
        control.replaceChildren(...values.map(value => {
            const option = document.createElement('option'); option.value = value;
            option.textContent = labels[value] ? `${labels[value]} (${value})` : value;
            option.selected = value === selected; return option;
        }));
    }
    function update() {
        const result = backyardBuilderResolve(Object.fromEntries(Object.entries(controls).map(([key,el]) => [key,el.value])), config);
        options(controls.field, result.fields, result.a.field);
        options(controls.output, result.outputs, result.a.output);
        Object.entries(result.a).forEach(([key,value]) => { controls[key].value = value; });
        for (const [key, visible] of Object.entries({identity:result.a.module === 'birds', species:result.species, rank:result.rank, format:result.date})) {
            backyardBuilder.querySelector(`[data-builder-row="${key}"]`).hidden = !visible;
            controls[key].disabled = !visible;
        }
        controls.period.disabled = result.today;
        backyardBuilder.querySelector('.backyard-builder-hint').textContent = result.a.type === 'stats'
            ? 'Statistieken gebruiken last_activity voor de laatste waarneming; last_seen hoort bij een soort. Vandaag-velden staan automatisch op vandaag.'
            : result.a.identity ? 'Alleen expliciet gemarkeerde Otje-waarnemingen. Haar foto is ook zonder waarnemingen beschikbaar.' : 'Afbeeldingen en links krijgen automatisch passende uitvoeropties.';
        backyardBuilder.querySelector('.backyard-builder-error').textContent = result.error;
        const copy = backyardBuilder.querySelector('.backyard-builder-copy');
        copy.disabled = !!result.error; copy.dataset.copy = result.code;
        copy.querySelector('code').textContent = result.code || 'Vul de ontbrekende gegevens in.';
    }
    backyardBuilder.addEventListener('input', update);
    backyardBuilder.addEventListener('change', update);
    update();
}
