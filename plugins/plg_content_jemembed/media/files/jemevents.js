// Event listener that runs when the HTML document is fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Reference container element once
    const container = document.getElementById('feed-container');

    fetch('/jemevents.php')
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            // Use optional chaining for safer nested property access
            const events = data?.data?.[0]?.data;

            if (Array.isArray(events) && events.length > 0) {
                // Create a document fragment to minimize DOM operations
                const fragment = document.createDocumentFragment();

                events.forEach(event => {
                    try {
                        const eventElement = createEventElement(event);
                        fragment.appendChild(eventElement);
                    } catch (err) {
                        console.error('Error creating event element:', err, event);
                        // Continue processing other events even if one fails
                    }
                });

                // Single DOM update with all events
                container.appendChild(fragment);
            } else {
                container.innerHTML = '<p>No events found.</p>';
            }
        })
        .catch(error => {
            console.error('Error fetching events:', error);
            container.innerHTML = `
                <p class="error-message">Unable to load events. Please try again later.</p>
            `;
            // Log detailed error for developers only
            console.error('Detailed error:', error);
        });
});

/**
 * Creates a DOM element for an event
 * @param {Object} event - The event data object
 * @return {HTMLElement} - The created event element
 */
function createEventElement(event) {
    // Validate event object
    if (!event) {
        throw new Error('Invalid event data');
    }

    const eventElement = document.createElement('div');
    eventElement.className = 'jemevent';

    // Generate HTML for different parts of the event
    const titleHTML = createItemHTML(event.title, createTitleText, createTitleLink);
    const venueHTML = createItemHTML(event.venue, createVenueText, createVenueLink);
    const categoriesHTML = createCategoriesHTML(event.categories);
    const descriptionText = getEventDescription(event.description);

    // Create date display with null checks
    const startDate = event.dates?.formatted_start_date || 'Datum unbekannt';
    const startTime = event.dates?.formatted_start_time ?
        `, ${event.dates.formatted_start_time}` : '';

    // Use innerHTML only once for performance
    eventElement.innerHTML = `
        ${titleHTML}
        <div class="jemevent-date">${startDate}${startTime}</div>
        ${venueHTML}
        ${categoriesHTML}
        <div class="jemevent-description">${escapeHTML(descriptionText)}</div>
    `;

    return eventElement;
}

/**
 * Escape HTML special characters to prevent XSS
 * @param {string} text - Text to escape
 * @return {string} - Escaped HTML text
 */
function escapeHTML(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Process event description with proper checks
 * @param {string} description - The event description
 * @return {string} - Truncated description or default text
 */
function getEventDescription(description) {
    const MAX_LENGTH = 150;
    if (!description) return 'Keine Beschreibung';
    return description.length > MAX_LENGTH ?
        `${description.substring(0, MAX_LENGTH)}...` : description;
}

/**
 * Generic function to handle different display modes
 * @param {Object} item - Item with display_mode property
 * @param {Function} textRenderer - Function to render text version
 * @param {Function} linkRenderer - Function to render link version
 * @return {string} - Rendered HTML based on display mode
 */
function createItemHTML(item, textRenderer, linkRenderer) {
    if (!item || item.display_mode === 'off') return '';

    if (item.display_mode === 'link') {
        return linkRenderer(item);
    } else if (item.display_mode === 'on') {
        return textRenderer(item);
    }

    return '';
}

/**
 * Creates text for the event title
 * @param {Object} title - The title data object
 * @return {string} - HTML string for the title text
 */
function createTitleText(title) {
    const titleText = title.display || title.full || 'Kein Titel';
    return `<h3 class="jemevent-title">${escapeHTML(titleText)}</h3>`;
}

/**
 * Creates link for the event title
 * @param {Object} title - The title data object
 * @return {string} - HTML string for the title link
 */
function createTitleLink(title) {
    const titleText = title.display || title.full || 'Kein Titel';
    return `<h3 class="jemevent-title"><a href="${encodeURI(title.url)}" target="_blank">${escapeHTML(titleText)}</a></h3>`;
}

/**
 * Creates text for the event venue
 * @param {Object} venue - The venue data object
 * @return {string} - HTML string for the venue text
 */
function createVenueText(venue) {
    const venueName = venue.name || 'Ort unbekannt';
    const venueCity = venue.city || '';
    const venueText = venueCity ? `${venueName}, ${venueCity}` : venueName;
    return `<div class="jemevent-venue">${escapeHTML(venueText)}</div>`;
}

/**
 * Creates link for the event venue
 * @param {Object} venue - The venue data object
 * @return {string} - HTML string for the venue link
 */
function createVenueLink(venue) {
    const venueName = venue.name || 'Ort unbekannt';
    const venueCity = venue.city || '';
    const venueText = venueCity ? `${venueName}, ${venueCity}` : venueName;
    return `<div class="jemevent-venue"><a href="${encodeURI(venue.url)}">${escapeHTML(venueText)}</a></div>`;
}

/**
 * Creates HTML for event categories based on their display modes
 * @param {Array} categories - Array of category objects
 * @return {string} - HTML string for the categories
 */
function createCategoriesHTML(categories) {
    if (!Array.isArray(categories) || categories.length === 0) return '';

    const visibleCategories = categories
        .filter(cat => cat && cat.display_mode !== 'off')
        .map(cat => createItemHTML(cat, createCategoryText, createCategoryLink))
        .filter(Boolean); // Remove empty strings

    if (visibleCategories.length > 0) {
        return `<div class="jemevent-categories">${visibleCategories.join(', ')}</div>`;
    }

    return '';
}

/**
 * Creates text for a category
 * @param {Object} category - The category data object
 * @return {string} - HTML string for the category text
 */
function createCategoryText(category) {
    return escapeHTML(category.name);
}

/**
 * Creates link for a category
 * @param {Object} category - The category data object
 * @return {string} - HTML string for the category link
 */
function createCategoryLink(category) {
    return `<a href="${encodeURI(category.url)}">${escapeHTML(category.name)}</a>`;
}
