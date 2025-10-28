jQuery(document).ready(function($) {
    
    // --- LÓGICA DE PESTAÑAS (TABS) ---
    
    function activateTab(tabHash) {
        // Si no hay hash (o es inválido), activa el primero
        if (!tabHash || $(tabHash).length === 0) {
            tabHash = $('.nav-tab-wrapper a.nav-tab').first().attr('href');
        }

        // Quitar clase activa de todas las pestañas y ocultar contenido
        $('.nav-tab').removeClass('nav-tab-active');
        $('.tab-content').hide();

        // Añadir clase activa a la pestaña correcta y mostrar su contenido
        $('a.nav-tab[href="' + tabHash + '"]').addClass('nav-tab-active');
        $(tabHash).show();
    }

    // Activar la pestaña al cargar la página (según el hash en la URL o la primera)
    activateTab(window.location.hash);

    // Manejar clic en una pestaña
    $('.nav-tab-wrapper a.nav-tab').on('click', function(e) {
        e.preventDefault();
        var tabHash = $(this).attr('href');
        activateTab(tabHash);
        
        // Actualizar el hash en la URL sin que la página salte
        if (history.pushState) {
            history.pushState(null, null, tabHash);
        } else {
            window.location.hash = tabHash;
        }
    });


    // --- LÓGICA DE EJECUCIÓN MANUAL (AJAX con Fetch) ---
    // Este es el código que ya probamos en el paso anterior, ahora en su archivo JS.
    
    const runButton = document.getElementById("autonews-manual-run-button");
    const logBox = document.getElementById("autonews-manual-log");
    const spinner = document.getElementById("dsrw_manual_spinner");

    // Comprobar que los elementos existen (solo se ejecutarán en la pestaña #tab-tools)
    if (runButton && logBox && spinner) {
        
        runButton.addEventListener("click", function(e) {
            e.preventDefault();
            
            // Mostrar spinner y limpiar log
            spinner.style.display = "inline-block";
            runButton.disabled = true;
            logBox.innerHTML = "⏳ Ejecutando feed manualmente...<br>";
            logBox.style.display = "block";

            // Usamos 'dsrwAjax.ajaxUrl' y 'dsrwAjax.nonce' que nos pasó wp_localize_script
            // 'fetch' es JS nativo, no necesita jQuery
            fetch(dsrwAjax.ajaxUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: new URLSearchParams({
                    action: "autonews_manual_run",
                    nonce:  dsrwAjax.nonce 
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (data.data.logs && data.data.logs.length > 0) {
                        data.data.logs.forEach(line => {
                            logBox.innerHTML += esc_html(line) + "<br>";
                        });
                    } else {
                        logBox.innerHTML += "✅ Proceso completado, pero no se generaron logs detallados.<br>";
                    }
                } else {
                    if (data.data.logs && data.data.logs.length > 0) {
                         data.data.logs.forEach(line => {
                            logBox.innerHTML += esc_html(line) + "<br>";
                        });
                    } else {
                        logBox.innerHTML += "❌ Error inesperado al ejecutar.<br>";
                    }
                }
                
                // Ocultar spinner y reactivar botón
                spinner.style.display = "none";
                runButton.disabled = false;
                logBox.scrollTop = logBox.scrollHeight; // Auto-scroll al final
            })
            .catch(error => {
                logBox.innerHTML += "❌ ERROR DE CONEXIÓN: " + esc_html(error.message) + "<br>";
                spinner.style.display = "none";
                runButton.disabled = false;
            });
        });
    }

    // Función simple para escapar HTML en JS y evitar XSS
    function esc_html(str) {
        if (typeof str !== 'string') return '';
        return str.replace(/[&<>"']/g, function(m) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[m];
        });
    }

    // --- ¡NUEVA LÓGICA! MANEJADOR DE LA BIBLIOTECA DE MEDIOS ---
    
    var mediaFrame;

    $('#dsrw-upload-bg-button').on('click', function(e) {
        e.preventDefault();

        // Si el frame ya existe, reabrirlo
        if (mediaFrame) {
            mediaFrame.open();
            return;
        }

        // Crear el frame de medios
        mediaFrame = wp.media({
            title: 'Elegir Imagen de Fondo',
            button: {
                text: 'Usar esta imagen'
            },
            multiple: false, // No permitir selección múltiple
            library: {
                type: 'image' // Solo mostrar imágenes
            }
        });

        // Cuando se selecciona una imagen
        mediaFrame.on('select', function() {
            var attachment = mediaFrame.state().get('selection').first().toJSON();
            
            // Poner el ID en el input oculto
            $('#dsrw_thumbnail_custom_bg_id').val(attachment.id);
            
            // Poner la URL en la vista previa (usar 'medium' o 'url')
            var previewUrl = attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
            $('#dsrw-bg-preview').attr('src', previewUrl);
            
            // Mostrar la vista previa y el botón de quitar
            $('.dsrw-bg-preview-wrapper').show();
            $('#dsrw-remove-bg-button').show();
        });

        // Abrir el frame
        mediaFrame.open();
    });

    // Manejar clic en el botón "Quitar Imagen"
    $('#dsrw-remove-bg-button').on('click', function(e) {
        e.preventDefault();
        
        // Limpiar el input oculto
        $('#dsrw_thumbnail_custom_bg_id').val('');
        
        // Limpiar la vista previa y ocultarla
        $('#dsrw-bg-preview').attr('src', '');
        $('.dsrw-bg-preview-wrapper').hide();
        
        // Ocultar este botón
        $(this).hide();
    });

});