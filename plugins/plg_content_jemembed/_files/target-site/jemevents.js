document.addEventListener('DOMContentLoaded', function() {
    fetch('/jemevents.php')
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('feed-container');
            
            if (data?.success && data?.data?.[0]?.data) {
                const events = data.data[0].data;
                
                events.forEach(event => {
                    const eventElement = document.createElement('div');
                    eventElement.className = 'event';
                    
                    // TITLE according to display_mode
                    let titleHTML = '';
                    if (event.title && event.title.display_mode !== 'off') {
                        const titleText = event.title?.display || event.title?.full || 'Kein Titel';
                        if (event.title.display_mode === 'link') {
                            titleHTML = `<h3 class="event-title"><a href="${event.title.url}">${titleText}</a></h3>`;
                        } else if (event.title.display_mode === 'on') {
                            titleHTML = `<h3 class="event-title">${titleText}</h3>`;
                        }
                    }
                    
                    // VENUE according to display_mode
                    let venueHTML = '';
                    if (event.venue && event.venue.display_mode !== 'off') {
                        const venueName = event.venue?.name || 'Ort unbekannt';
                        const venueCity = event.venue?.city || '';
                        const venueText = venueCity ? `${venueName}, ${venueCity}` : venueName;
                        
                        if (event.venue.display_mode === 'link') {
                            venueHTML = `<div class="event-venue"><a href="${event.venue.url}">${venueText}</a></div>`;
                        } else if (event.venue.display_mode === 'on') {
                            venueHTML = `<div class="event-venue">${venueText}</div>`;
                        }
                    }
                    
                    // KATEGORIEN according to display_mode
                    let categoriesHTML = '';
                    if (event.categories && event.categories.length > 0) {
                        const visibleCategories = event.categories
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
                            categoriesHTML = `<div class="event-categories">${visibleCategories.join(', ')}</div>`;
                        }
                    }
                    
                    eventElement.innerHTML = `
                        ${titleHTML}
                        <div class="event-date">
                            ${event.dates?.formatted_start_date || 'Datum unbekannt'}
                            ${event.dates?.formatted_start_time ? ', ' + event.dates.formatted_start_time : ''}
                        </div>
                        ${venueHTML}
                        ${categoriesHTML}
                        <div class="event-description">${event.description?.substring(0, 150) || 'Keine Beschreibung'}...</div>
                    `;
                    
                    container.appendChild(eventElement);
                });
            } else {
                container.innerHTML = '<p>Keine Events gefunden oder Datenformat ungültig.</p>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('feed-container').innerHTML = `
                <p style="color: red;">Error: ${error.message}</p>
            `;
        });
});