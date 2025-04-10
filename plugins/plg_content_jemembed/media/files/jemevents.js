// Event listener that runs when the HTML document is fully loaded
document.addEventListener('DOMContentLoaded', function() {
   // Fetch events data from the server
    fetch('/jemevents.php')
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('feed-container');
           // Check if the data is valid and contains events
            if (data?.success && data?.data?.[0]?.data) {
                const events = data.data[0].data;
               // Loop through all events and add them to the container
                events.forEach(event => {
                    const eventElement = createEventElement(event);
                    container.appendChild(eventElement);
                });
            } else {
               // Display a message if no events are found or data format is invalid
                container.innerHTML = '<p>No events or invalid data format.</p>';
            }
        })
        .catch(error => {
           // Handle any errors during the fetch operation
            console.error('Error:', error);
            document.getElementById('feed-container').innerHTML = `
                <p style="color: red;">Error: ${error.message}</p>
            `;
        });
});

/**
* Creates a DOM element for an event
* @param {Object} event - The event data object
* @return {HTMLElement} - The created event element
*/
function createEventElement(event) {
   // Create a new div element for the event
    const eventElement = document.createElement('div');
    eventElement.className = 'jemevent';

   // Generate HTML for different parts of the event
    const titleHTML = createTitleHTML(event.title);
    const venueHTML = createVenueHTML(event.venue);
    const categoriesHTML = createCategoriesHTML(event.categories);

   // Combine all parts into the event element
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

/**
* Creates HTML for the event title based on its display mode
* @param {Object} title - The title data object
* @return {string} - HTML string for the title
*/
function createTitleHTML(title) {
   // Return empty string if title is missing or display mode is off
    if (!title || title.display_mode === 'off') return '';

   // Use available title text or default
    const titleText = title.display || title.full || 'Kein Titel';
   
   // Create link if display mode is 'link'
    if (title.display_mode === 'link') {
        return `<h3 class="jemevent-title"><a href="${title.url}" target="_blank">${titleText}</a></h3>`;
    } else if (title.display_mode === 'on') {
       // Display title text without link
        return `<h3 class="jemevent-title">${titleText}</h3>`;
    }
    return '';
}

/**
* Creates HTML for the event venue based on its display mode
* @param {Object} venue - The venue data object
* @return {string} - HTML string for the venue
*/
function createVenueHTML(venue) {
   // Return empty string if venue is missing or display mode is off
    if (!venue || venue.display_mode === 'off') return '';

   // Use available venue information or defaults
    const venueName = venue.name || 'Ort unbekannt';
    const venueCity = venue.city || '';
    const venueText = venueCity ? `${venueName}, ${venueCity}` : venueName;

   // Create link if display mode is 'link'
    if (venue.display_mode === 'link') {
        return `<div class="jemevent-venue"><a href="${venue.url}">${venueText}</a></div>`;
    } else if (venue.display_mode === 'on') {
       // Display venue text without link
        return `<div class="jemevent-venue">${venueText}</div>`;
    }
    return '';
}

/**
* Creates HTML for event categories based on their display modes
* @param {Array} categories - Array of category objects
* @return {string} - HTML string for the categories
*/
function createCategoriesHTML(categories) {
   // Return empty string if no categories exist
    if (!categories || categories.length === 0) return '';

   // Filter and format visible categories
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

   // Create HTML for categories if any are visible
    if (visibleCategories.length > 0) {
        return `<div class="jemevent-categories">${visibleCategories.join(', ')}</div>`;
    }
    return '';
}
