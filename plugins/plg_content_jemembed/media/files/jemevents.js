document.addEventListener('DOMContentLoaded', function() {
    fetch('/jemevents.php')
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('feed-container');

            if (data?.success && data?.data?.[0]?.data) {
                const events = data.data[0].data;

                events.forEach(event => {
                    const eventElement = createEventElement(event);
                    container.appendChild(eventElement);
                });
            } else {
                container.innerHTML = '<p>No events or invalid data format.</p>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('feed-container').innerHTML = `
                <p style="color: red;">Error: ${error.message}</p>
            `;
        });
});

function createEventElement(event) {
    const eventElement = document.createElement('div');
    eventElement.className = 'jemevent';

    const titleHTML = createTitleHTML(event.title);
    const venueHTML = createVenueHTML(event.venue);
    const categoriesHTML = createCategoriesHTML(event.categories);

    eventElement.innerHTML = `
        ${titleHTML}
        <div class="jemevent-date">
            ${event.dates?.formatted_start_date || 'Datum unbekannt'}
            ${event.dates?.formatted_start_time ? ', ' + event.dates.formatted_start_time : ''}
        </div>
        ${venueHTML}
        ${categoriesHTML}
        <div class="jemevent-description">${event.description?.substring(0, 150) || 'Keine Beschreibung'}...</div>
    `;

    return eventElement;
}

function createTitleHTML(title) {
    if (!title || title.display_mode === 'off') return '';

    const titleText = title.display || title.full || 'Kein Titel';
    if (title.display_mode === 'link') {
        return `<h3 class="jemevent-title"><a href="${title.url}" target="_blank">${titleText}</a></h3>`;
    } else if (title.display_mode === 'on') {
        return `<h3 class="jemevent-title">${titleText}</h3>`;
    }
    return '';
}

function createVenueHTML(venue) {
    if (!venue || venue.display_mode === 'off') return '';

    const venueName = venue.name || 'Ort unbekannt';
    const venueCity = venue.city || '';
    const venueText = venueCity ? `${venueName}, ${venueCity}` : venueName;

    if (venue.display_mode === 'link') {
        return `<div class="jemevent-venue"><a href="${venue.url}">${venueText}</a></div>`;
    } else if (venue.display_mode === 'on') {
        return `<div class="jemevent-venue">${venueText}</div>`;
    }
    return '';
}

function createCategoriesHTML(categories) {
    if (!categories || categories.length === 0) return '';

    const visibleCategories = categories
        .filter(cat => cat.display_mode !== 'off')
        .map(cat => {
            if (cat.display_mode === 'link') {
                return `<a href="${cat.url}">${cat.name}</a>`;
            } else if (cat.display_mode === 'on') {
                return cat.name;
            }
            return '';
        })
        .filter(Boolean);

    if (visibleCategories.length > 0) {
        return `<div class="jemevent-categories">${visibleCategories.join(', ')}</div>`;
    }
    return '';
}
