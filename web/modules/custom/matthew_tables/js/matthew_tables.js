(function ($, Drupal) {
  Drupal.behaviors.matthewTables = {
    attach: function (context, settings) {
      $(once('matthew-tables', 'form#matthew-tables-form', context)).each(function () {
        function calculateTotals(row) {
          var $row = $(row);
          var q1 = parseFloat($row.find('input[name*="[jan]"]').val() || 0) +
            parseFloat($row.find('input[name*="[feb]"]').val() || 0) +
            parseFloat($row.find('input[name*="[mar]"]').val() || 0);
          var q2 = parseFloat($row.find('input[name*="[apr]"]').val() || 0) +
            parseFloat($row.find('input[name*="[may]"]').val() || 0) +
            parseFloat($row.find('input[name*="[jun]"]').val() || 0);
          var q3 = parseFloat($row.find('input[name*="[jul]"]').val() || 0) +
            parseFloat($row.find('input[name*="[aug]"]').val() || 0) +
            parseFloat($row.find('input[name*="[sep]"]').val() || 0);
          var q4 = parseFloat($row.find('input[name*="[oct]"]').val() || 0) +
            parseFloat($row.find('input[name*="[nov]"]').val() || 0) +
            parseFloat($row.find('input[name*="[dec]"]').val() || 0);
          var ytd = q1 + q2 + q3 + q4;

          $row.find('span[data-quarter="q1"]').text(q1.toFixed(2));
          $row.find('span[data-quarter="q2"]').text(q2.toFixed(2));
          $row.find('span[data-quarter="q3"]').text(q3.toFixed(2));
          $row.find('span[data-quarter="q4"]').text(q4.toFixed(2));
          $row.find('span.ytd-total').text(ytd.toFixed(2));
        }

        $('table input[type="number"]').on('input', function() {
          calculateTotals($(this).closest('tr'));
        });

        $('table tr').each(function() {
          calculateTotals(this);
        });
      });
    }
  };
})(jQuery, Drupal);
