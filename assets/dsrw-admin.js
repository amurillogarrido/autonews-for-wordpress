jQuery(document).ready(function($) {
    let $manualButton = $('#dsrw_manual_process_button');
    let $spinner      = $('#dsrw_manual_spinner');
    let $statusArea   = $('#dsrw_manual_status');

    $manualButton.on('click', function(e) {
        e.preventDefault();

        // Mostrar spinner
        $spinner.show();
        $statusArea.html('Procesando via AJAX...');

        // Deshabilitar el botón
        $manualButton.prop('disabled', true);

        // Llamada AJAX (lo implementas en dsrw_run_feeds)
        $.post(dsrwAjax.ajaxUrl, {
            action: 'dsrw_run_feeds',
            _ajax_nonce: dsrwAjax.nonce
        })
        .done(function(response) {
            if (response.success) {
                $statusArea.html('¡Procesamiento completado con éxito!');
            } else {
                $statusArea.html('Error: ' + (response.data ? response.data.message : 'Desconocido'));
            }
        })
        .fail(function(xhr) {
            $statusArea.html('Fallo en la petición AJAX: ' + xhr.statusText);
        })
        .always(function() {
            $spinner.hide();
            $manualButton.prop('disabled', false);
        });
    });
});
