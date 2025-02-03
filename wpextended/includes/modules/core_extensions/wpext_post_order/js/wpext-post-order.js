jQuery(function ($) {
  const container = $('#the-list');
  const th = $('#wpext_order');
  let startIndex,
    stopIndex,
    order = [];

  container.sortable({
    start: function (e, ui) {
      startIndex = ui.item.index();
      var screenpage = jQuery('.screen-per-page').val();
      var currentpage = jQuery('.current-page').val();

      container
        .children()
        .not('.ui-sortable-placeholder')
        .each(function (n, el) {
          let index_increment = n + 1;
          let current_number = (currentpage - 1) * screenpage;
          if (currentpage > 1) {
            n = index_increment + current_number;
            order.push(n);
          } else {
            order.push(n + 1);
          }
        });

      const ph = container.find('.ui-sortable-placeholder'),
        phtd = ph.find('td'),
        tr = container.find('tr').not(ph).first();

      tr.children().each(function (i, e) {
        if ($(e).css('display') === 'none') {
          phtd.eq(i).hide();
        }
      });
    },
    stop: function (e, ui) {
      stopIndex = ui.item.index();

      if (startIndex != stopIndex) {
        const toSave = [];
        const rows = container.children().not('.ui-sortable-placeholder'),
          start = Math.min(startIndex, stopIndex),
          stop = Math.max(startIndex, stopIndex);

        for (let i = start; i <= stop; i++) {
          toSave.push({
            id: rows
              .eq(i)
              .attr('id')
              .replace(/^[^\d]+/g, ''),
            order: order[i],
          });
        }

        updatePosts(toSave);
      }
    },
  });

  async function updatePosts(list) {
    try {
      const data = new FormData();

      // Add post type
      data.append('post_type', window.typenow || 'post');

      // Add items
      list.forEach((item, index) => {
        data.append(`items[${index}][id]`, item.id);
        data.append(`items[${index}][order]`, item.order);
      });

      const response = await fetch(wpextPostOrder.root + 'wpext/v1/reorder', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'X-WP-Nonce': wpextPostOrder.nonce,
        },
        body: data,
      });

      if (!response.ok) {
        throw new Error('Network response was not ok');
      }

      const result = await response.json();

      if (result.status && result.saved) {
        result.saved.forEach((item) => {
          $('#post-' + item.id + ' .wpext_order').text(item.order);
        });
      } else if (!result.status) {
        console.error('Reorder failed:', result.error);
        alert('Failed to update post order. Please try again.');
      }

      if (result.errors && result.errors.length > 0) {
        console.warn('Some items failed to update:', result.errors);
      }
    } catch (error) {
      console.error('Error updating post order:', error);
      alert('Failed to update post order. Please try again.');

      // Optionally reload the page to reset the order
      window.location.reload();
    }
  }
});
