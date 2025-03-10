<?php
require_once 'config.php';

// Log para depuración
function logDebug($message) {
    error_log("[DEBUG " . date('Y-m-d H:i:s') . "] $message\n", 3, 'debug.log');
}

session_name('mi_app_sesion');
session_start();

if (isset($_GET['logout'])) {
    session_destroy();
    setcookie('access_token', '', time() - 3600, "/");
    header('Location: index.php');
    exit;
}

if (!isset($_SESSION['mi_app_access_token']) && isset($_COOKIE['access_token'])) {
    $_SESSION['mi_app_access_token'] = $_COOKIE['access_token'];
    logDebug("Token restaurado desde cookie.");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cliente OpenEMR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Cliente OpenEMR</h1>

        <?php if (!isset($_SESSION['mi_app_access_token']) || empty($_SESSION['mi_app_access_token'])): ?>
            <?php logDebug("No hay access_token en la sesión"); ?>
            <a href="<?php echo AUTH_URL . '?response_type=code&client_id=' . CLIENT_ID . '&redirect_uri=' . REDIRECT_URI . '&scope=' . urlencode(implode(' ', $scopes)) . '&state=12345'; ?>" class="btn btn-primary">
                Iniciar sesión con OpenEMR
            </a>
        <?php else: ?>
            <?php logDebug("Access_token presente: " . $_SESSION['mi_app_access_token']); ?>
            <div class="mb-3">
                <a href="index.php?logout=1" class="btn btn-secondary">Cerrar sesión</a>
            </div>
            <!-- Formulario para buscar pacientes -->
            <div class="card mb-4">
                <div class="card-header">Buscar Paciente</div>
                <div class="card-body">
                    <div class="mb-3 d-flex align-items-center">
                        <label class="me-2">Buscar por:</label>
                        <select id="searchType" class="form-select me-2" style="width: auto;">
                            <option value="family">Apellido</option>
                            <option value="identifier">Documento</option>
                        </select>
                        <input type="text" id="searchQuery" class="form-control" placeholder="Ingrese el valor a buscar">
                    </div>
                    <button id="searchBtn" class="btn btn-primary">Buscar</button>
                    <div id="searchResults" class="mt-3"></div>
                </div>
            </div>

            <!-- Formulario para registrar pacientes (oculto inicialmente) -->
            <div class="card" id="registerCard" style="display: none;">
                <div class="card-header">Registrar Paciente</div>
                <div class="card-body">
                    <form id="registerForm">
                        <div class="mb-3">
                            <label for="firstName" class="form-label">Primer Nombre</label>
                            <input type="text" class="form-control" id="firstName" name="firstName" required>
                        </div>
                        <div class="mb-3">
                            <label for="middleName" class="form-label">Segundo Nombre</label>
                            <input type="text" class="form-control" id="middleName" name="middleName">
                        </div>
                        <div class="mb-3">
                            <label for="lastName" class="form-label">Apellido</label>
                            <input type="text" class="form-control" id="lastName" name="lastName" required>
                        </div>
                        <div class="mb-3">
                            <label for="gender" class="form-label">Sexo</label>
                            <select class="form-select" id="gender" name="gender" required>
                                <option value="">Seleccione una opción</option>
                                <option value="Male">Hombre</option>
                                <option value="Female">Mujer</option>
                                <option value="non_binary">No binario</option>
                                <option value="UNK">Desconocido</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="document" class="form-label">Documento</label>
                            <input type="text" class="form-control" id="document" name="document" required>
                        </div>
                        <div class="mb-3">
                            <label for="birthDate" class="form-label">Fecha de Nacimiento</label>
                            <input type="date" class="form-control" id="birthDate" name="birthDate" required>
                        </div>
                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-success">Registrar</button>
                            <button type="button" id="cancelRegisterBtn" class="btn btn-secondary">Cancelar</button>
                        </div>
                    </form>
                    <div id="registerResult" class="mt-3"></div>
                </div>
            </div>

            <!-- Modal para paciente existente -->
            <div class="modal fade" id="existingPatientModal" tabindex="-1" aria-labelledby="existingPatientModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="existingPatientModalLabel">¡Paciente ya existe!</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div id="existingPatientData"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        $(document).ready(function() {
            $('#searchBtn').click(function() {
                const query = $('#searchQuery').val();
                const searchType = $('#searchType').val();
                if (!query) {
                    $('#searchResults').html('<p class="text-danger">Debe ingresar un valor para buscar.</p>');
                    return;
                }

                $.ajax({
                    url: 'api.php',
                    method: 'GET',
                    data: { action: 'search', query: query, searchType: searchType },
                    dataType: 'json',
                    success: function(response) {
                        console.log("Respuesta exitosa:", response);
                        if (response.error) {
                            $('#searchResults').html('<p class="text-danger">' + response.error + '</p>');
                            $('#registerCard').show();
                        } else if (response.entry && response.entry.length > 0) {
                            let tableHtml = '<table class="table table-striped">';
                            tableHtml += '<thead><tr>';
                            tableHtml += '<th>Apellido y Nombre</th><th>Documento</th><th>Sexo</th><th>Fecha Nacimiento</th><th>Nro. de Móvil</th>';
                            tableHtml += '</tr></thead><tbody>';

                            response.entry.forEach(function(entry) {
                                const patient = entry.resource;

                                const nameData = patient.name && patient.name[0] ? patient.name[0] : {};
                                const family = nameData.family || 'N/A';
                                const given = nameData.given ? nameData.given.join(' ') : 'N/A';
                                const fullName = `${family}, ${given}`;

                                let document = 'N/A';
                                if (patient.identifier) {
                                    const ptIdentifier = patient.identifier.find(id => 
                                        id.type && id.type.coding && id.type.coding.some(c => c.code === 'PT')
                                    );
                                    document = ptIdentifier ? ptIdentifier.value : 'N/A';
                                }

                                const gender = patient.gender ? patient.gender.charAt(0).toUpperCase() + patient.gender.slice(1) : 'N/A';
                                const birthDate = patient.birthDate || 'N/A';

                                let mobile = 'N/A';
                                if (patient.telecom) {
                                    const mobileData = patient.telecom.find(t => t.system === 'phone' && t.use === 'mobile');
                                    mobile = mobileData ? mobileData.value : 'N/A';
                                }

                                tableHtml += '<tr>';
                                tableHtml += `<td>${fullName}</td>`;
                                tableHtml += `<td>${document}</td>`;
                                tableHtml += `<td>${gender}</td>`;
                                tableHtml += `<td>${birthDate}</td>`;
                                tableHtml += `<td>${mobile}</td>`;
                                tableHtml += '</tr>';
                            });

                            tableHtml += '</tbody></table>';
                            $('#searchResults').html(tableHtml);
                            $('#registerCard').hide();
                        } else {
                            $('#searchResults').html('<p>No se encontraron pacientes con ese criterio. ¿Desea registrar uno nuevo?</p>');
                            $('#registerCard').show();
                        }
                    },
                    error: function(xhr) {
                        console.error("Error en la petición:", xhr.status, xhr.responseText);
                        $('#searchResults').html('<p class="text-danger">Error al buscar pacientes.</p>');
                    }
                });
            });

            $('#registerForm').submit(function(e) {
                e.preventDefault();
                const firstName = $('#firstName').val();
                const middleName = $('#middleName').val();
                const lastName = $('#lastName').val();
                const gender = $('#gender').val();
                const document = $('#document').val();
                const birthDate = $('#birthDate').val();

                if (!firstName || !lastName || !gender || !document || !birthDate) {
                    let missingFields = [];
                    if (!firstName) missingFields.push("Primer Nombre");
                    if (!lastName) missingFields.push("Apellido");
                    if (!gender) missingFields.push("Sexo");
                    if (!document) missingFields.push("Documento");
                    if (!birthDate) missingFields.push("Fecha de Nacimiento");
                    alert("Los siguientes campos son obligatorios: " + missingFields.join(", "));
                    return;
                }

                $.ajax({
                    url: 'api.php',
                    method: 'GET',
                    data: { action: 'check_document', document: document },
                    dataType: 'json',
                    success: function(response) {
                        if (response.exists && response.entry && response.entry.length > 0) {
                            let tableHtml = '<table class="table table-striped">';
                            tableHtml += '<thead><tr>';
                            tableHtml += '<th>Apellido y Nombre</th><th>Documento</th><th>Sexo</th><th>Fecha Nacimiento</th><th>Nro. de Móvil</th>';
                            tableHtml += '</tr></thead><tbody>';

                            response.entry.forEach(function(entry) {
                                const patient = entry.resource;

                                const nameData = patient.name && patient.name[0] ? patient.name[0] : {};
                                const family = nameData.family || 'N/A';
                                const given = nameData.given ? nameData.given.join(' ') : 'N/A';
                                const fullName = `${family}, ${given}`;

                                let document = 'N/A';
                                if (patient.identifier) {
                                    const ptIdentifier = patient.identifier.find(id => 
                                        id.type && id.type.coding && id.type.coding.some(c => c.code === 'PT')
                                    );
                                    document = ptIdentifier ? ptIdentifier.value : 'N/A';
                                }

                                const gender = patient.gender ? patient.gender.charAt(0).toUpperCase() + patient.gender.slice(1) : 'N/A';
                                const birthDate = patient.birthDate || 'N/A';

                                let mobile = 'N/A';
                                if (patient.telecom) {
                                    const mobileData = patient.telecom.find(t => t.system === 'phone' && t.use === 'mobile');
                                    mobile = mobileData ? mobileData.value : 'N/A';
                                }

                                tableHtml += '<tr>';
                                tableHtml += `<td>${fullName}</td>`;
                                tableHtml += `<td>${document}</td>`;
                                tableHtml += `<td>${gender}</td>`;
                                tableHtml += `<td>${birthDate}</td>`;
                                tableHtml += `<td>${mobile}</td>`;
                                tableHtml += '</tr>';
                            });

                            tableHtml += '</tbody></table>';
                            $('#existingPatientData').html(tableHtml);
                            $('#existingPatientModal').modal('show');

                            $('#existingPatientModal').on('hidden.bs.modal', function () {
                                $('#registerCard').hide();
                                $('#searchResults').html('');
                            });
                        } else {
                            const data = {
                                action: 'register',
                                firstName: firstName,
                                middleName: middleName,
                                lastName: lastName,
                                gender: gender,
                                document: document,
                                birthDate: birthDate
                            };

                            $.ajax({
                                url: 'api.php',
                                method: 'POST',
                                data: data,
                                dataType: 'json',
                                success: function(response) {
                                    console.log("Respuesta al registrar paciente:", response);
                                    if (response.error) {
                                        $('#registerResult').html('<p class="text-danger">' + response.error + '</p>');
                                        console.error("Detalles del error:", response.details);
                                    } else {
                                        $('#registerResult').html('<p class="text-success">' + response.message + '</p>');
                                        $('#registerCard').hide();
                                    }
                                },
                                error: function(xhr) {
                                    console.error("Error en la petición:", xhr.status, xhr.responseText);
                                    $('#registerResult').html('<p class="text-danger">Error al registrar paciente.</p>');
                                }
                            });
                        }
                    },
                    error: function(xhr) {
                        console.error("Error al verificar documento:", xhr.status, xhr.responseText);
                        $('#registerResult').html('<p class="text-danger">Error al verificar documento.</p>');
                    }
                });
            });

            // Evento para el botón Cancelar
            $('#cancelRegisterBtn').click(function() {
                $('#registerCard').hide();
                $('#searchResults').html(''); // Limpia los resultados para volver al estado inicial
            });
        });
    </script>
</body>
</html>