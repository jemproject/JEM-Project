/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

jQuery(document).ready(function($) {
    const $loadMoreBtn = $('#jem-load-more-btn');
    const $eventList = $('.eventlist');
    let nextOffset = Number.parseInt($loadMoreBtn.attr('data-next-offset') || '0', 10);
    const limit = Number.parseInt($loadMoreBtn.attr('data-limit') || '10', 10);
    const endpoint = $loadMoreBtn.attr('data-endpoint') || '';
    const context = $loadMoreBtn.attr('data-context') || '';
    const textLoading = $loadMoreBtn.attr('data-text-loading') || 'Loading...';
    const textLoadMore = $loadMoreBtn.attr('data-text-loadmore') || 'Load More';
    let lastDisplayedMonth = $('.eventlist .row-month').last().text().trim();
    let isLoading = false;

    $(document).on('click', '[data-jem-event-url]', function(event) {
        if ($(event.target).closest('a, button, input, select, textarea, label').length) {
            return;
        }

        try {
            const target = new URL($(this).attr('data-jem-event-url'), window.location.href);

            if ((target.protocol === 'http:' || target.protocol === 'https:')
                && target.origin === window.location.origin) {
                window.location.assign(target.href);
            }
        } catch (error) {
            // Ignore invalid navigation targets.
        }
    });

    function animateEvents($events) {
        $events.each(function(index) {
            const $event = $(this);
            $event.css({
                'opacity': '0',
                'transform': 'translateY(20px)',
                'transition': 'opacity 0.4s ease, transform 0.4s ease'
            });

            setTimeout(function() {
                $event.css({
                    'opacity': '1',
                    'transform': 'translateY(0)'
                });
            }, index * 150);
        });
    }

    if (!$loadMoreBtn.length || !endpoint || !Number.isInteger(nextOffset)
        || !Number.isInteger(limit) || nextOffset < 0 || limit < 1) {
        return;
    }

    $loadMoreBtn.on('click', function(event) {
        event.preventDefault();

        if (isLoading) {
            return;
        }

        isLoading = true;
        $loadMoreBtn.text(textLoading).prop('disabled', true);

        let requestUrl;

        try {
            requestUrl = new URL(endpoint, window.location.href);
            requestUrl.searchParams.set('offset', String(nextOffset));
            requestUrl.searchParams.set('limit', String(limit));

            if (lastDisplayedMonth) {
                requestUrl.searchParams.set('lastDisplayedMonth', lastDisplayedMonth);
            } else {
                requestUrl.searchParams.delete('lastDisplayedMonth');
            }

            if (context === 'archive') {
                requestUrl.searchParams.set('loadmore_context', 'archive');
            } else {
                requestUrl.searchParams.delete('loadmore_context');
            }
        } catch (error) {
            $loadMoreBtn.hide();
            isLoading = false;
            return;
        }

        $.ajax({
            url: requestUrl.toString(),
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                const payload = response && response.data ? response.data : response;

                if (!payload || typeof payload !== 'object') {
                    $loadMoreBtn.hide();
                    return;
                }

                if (typeof payload.html === 'string' && payload.html.trim()) {
                    const $newEvents = $(payload.html);
                    $eventList.append($newEvents);
                    animateEvents($newEvents);
                }

                if (typeof payload.lastDisplayedMonth === 'string') {
                    lastDisplayedMonth = payload.lastDisplayedMonth;
                }

                if (Number.isInteger(payload.nextOffset) && payload.nextOffset >= 0) {
                    nextOffset = payload.nextOffset;
                    $loadMoreBtn.attr('data-next-offset', String(nextOffset));
                }

                if (!payload.hasMore) {
                    $loadMoreBtn.hide();
                }
            },
            error: function() {
                $loadMoreBtn.hide();
            },
            complete: function() {
                isLoading = false;
                $loadMoreBtn.text(textLoadMore).prop('disabled', false);
            }
        });
    });
});
