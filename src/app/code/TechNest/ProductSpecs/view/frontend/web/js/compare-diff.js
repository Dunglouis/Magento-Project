/**
 * Compare page: marks attribute rows whose values differ between the products (.tn-diff)
 * and lets the shopper hide the rows that are the same.
 */
define(['jquery'], function ($) {
    'use strict';

    function text(cell) {
        return $(cell).text().replace(/\s+/g, ' ').trim().toLowerCase();
    }

    return function (config, element) {
        var $switch = $(element),
            $table = $(config.table),
            $rows = $table.find('tbody tr').filter(function () {
                return $(this).find('td.cell.product.attribute').length > 0;
            }),
            differ = 0,
            $count = $switch.find('[data-role=diff-count]');

        if (!$rows.length || $rows.first().find('td.cell.product.attribute').length < 2) {
            return;
        }

        $rows.addClass('tn-attr').each(function () {
            var values = $(this).find('td.cell.product.attribute').map(function () {
                return text(this);
            }).get();

            if (values.some(function (value) { return value !== values[0]; })) {
                $(this).addClass('tn-diff');
                differ++;
            }
        });

        $count.text($count.data('template').replace('%1', differ).replace('%2', $rows.length));
        $switch.prop('hidden', false).insertBefore($table.closest('.table-wrapper').length
            ? $table.closest('.table-wrapper') : $table);

        $switch.find('[data-role=only-diff]').on('change', function () {
            $table.toggleClass('tn-only-diff', this.checked);
        });
    };
});
