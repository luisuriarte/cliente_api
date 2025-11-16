<?php
require_once 'config.php';

// Habilitar depuración de errores PHP (desactivar en producción)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function logDebug($message) {
    error_log("[DEBUG " . date('Y-m-d H:i:s') . "] $message\n", 3, 'debug.log');
}

session_name('mi_app_sesion');
session_start();

// Manejar el callback de autorización OAuth2
if (isset($_GET['code'])) {
    $code = $_GET['code'];
    logDebug("Código de autorización recibido: $code");

    $url = TOKEN_URL;
    $data = [
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => REDIRECT_URI,
        'client_id' => CLIENT_ID,
        'client_secret' => CLIENT_SECRET
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Accept: application/json",
        "Content-Type: application/x-www-form-urlencoded"
    ]);

    $response = curl_exec($ch);
    if ($response === false) {
        logDebug("Error en cURL: " . curl_error($ch));
        die("Error al conectar con el servidor de autenticación.");
    }

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        $tokenData = json_decode($response, true);
        if (isset($tokenData['access_token'])) {
            $_SESSION['mi_app_access_token'] = $tokenData['access_token'];
            setcookie('access_token', $tokenData['access_token'], time() + 3600, "/");
            logDebug("Access_token obtenido: " . substr($tokenData['access_token'], 0, 30) . "...");
            header('Location: index.php');
            exit;
        }
    }
    logDebug("Error al obtener token. HTTP Code: $http_code. Respuesta: $response");
    die("Error al obtener el token de acceso.");
}

// Verificar token
$accessToken = $_SESSION['mi_app_access_token'] ?? $_COOKIE['access_token'] ?? null;
if (!$accessToken) {
    header('Content-Type: application/json');
    echo json_encode(["error" => "No hay token de acceso"]);
    exit;
}

header('Content-Type: application/json');

// Manejar solicitudes
$method = $_SERVER['REQUEST_METHOD'];
$action = $_REQUEST['action'] ?? '';

if ($method === 'GET' && $action === 'search') {
    $query = $_REQUEST['query'] ?? '';
    $searchType = $_REQUEST['searchType'] ?? 'family';
    if (empty($query)) {
        logDebug("Error: Query vacío en búsqueda.");
        echo json_encode(["error" => "Debe proporcionar un valor para buscar."]);
        exit;
    }

    logDebug("Buscando paciente con query: $query, tipo: $searchType");

    $url = "https://openemr-domain/apis/default/fhir/Patient?";
    if ($searchType === 'family') {
        $url .= "family=" . urlencode($query);
    } elseif ($searchType === 'identifier') {
        $system = "http://terminology.hl7.org/CodeSystem/v2-0203";
        $url .= "identifier=" . urlencode($system . "|" . $query);
    } else {
        logDebug("Error: searchType inválido: $searchType");
        echo json_encode(["error" => "Tipo de búsqueda no válido."]);
        exit;
    }

    $headers = [
        "Authorization: Bearer $accessToken",
        "Accept: application/json"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    if ($response === false) {
        logDebug("Error en cURL: " . curl_error($ch));
        echo json_encode(["error" => "Error en la búsqueda de pacientes."]);
        exit;
    }

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        logDebug("Error en búsqueda de paciente. HTTP Code: $http_code. Respuesta: $response");
        echo json_encode(["error" => "Error al buscar paciente.", "details" => $response]);
        exit;
    }

    logDebug("Búsqueda exitosa: " . substr($response, 0, 100) . "...");
    echo $response;
    exit;
}

if ($method === 'GET' && $action === 'check_document') {
    $document = $_REQUEST['document'] ?? '';
    if (empty($document)) {
        logDebug("Error: Documento vacío en verificación.");
        echo json_encode(["error" => "Debe proporcionar un documento para verificar."]);
        exit;
    }

    logDebug("Verificando documento: $document");

    $system = "http://terminology.hl7.org/CodeSystem/v2-0203";
    $url = "https://openemr-domain/apis/default/fhir/Patient?identifier=" . urlencode($system . "|" . $document);

    $headers = [
        "Authorization: Bearer $accessToken",
        "Accept: application/json"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    if ($response === false) {
        logDebug("Error en cURL: " . curl_error($ch));
        echo json_encode(["error" => "Error al verificar documento."]);
        exit;
    }

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $responseData = json_decode($response, true);
    curl_close($ch);

    if ($http_code !== 200) {
        logDebug("Error al verificar documento. HTTP Code: $http_code. Respuesta: $response");
        echo json_encode(["error" => "Error al verificar documento.", "details" => $response]);
        exit;
    }

    $exists = !empty($responseData['entry']) && count($responseData['entry']) > 0;
    logDebug("Resultado de verificación: " . ($exists ? "Paciente existe" : "Paciente no existe"));
    echo json_encode(array_merge(["exists" => $exists], $responseData));
    exit;
}

if ($method === 'POST' && $action === 'register') {
    $firstName = $_POST['firstName'] ?? '';
    $middleName = $_POST['middleName'] ?? ''; // Puede estar vacío
    $lastName = $_POST['lastName'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $document = $_POST['document'] ?? '';
    $birthDate = $_POST['birthDate'] ?? '';

    // Quitamos middleName de la validación de campos obligatorios
    if (empty($firstName) || empty($lastName) || empty($gender) || empty($document) || empty($birthDate)) {
        logDebug("Error: Datos incompletos para registrar paciente.");
        echo json_encode(["error" => "Todos los campos obligatorios deben estar completos."]);
        exit;
    }

    // Verificación adicional en el backend (como respaldo)
    $system = "http://terminology.hl7.org/CodeSystem/v2-0203";
    $checkUrl = "https://openemr-domain/apis/default/fhir/Patient?identifier=" . urlencode($system . "|" . $document);
    $headers = [
        "Authorization: Bearer $accessToken",
        "Accept: application/json"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $checkUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $checkResponse = curl_exec($ch);
    $checkHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $checkData = json_decode($checkResponse, true);
    curl_close($ch);

    if ($checkHttpCode === 200 && !empty($checkData['entry']) && count($checkData['entry']) > 0) {
        logDebug("Error: Intento de registrar paciente con documento existente: $document");
        echo json_encode(["error" => "El paciente con este documento ya existe.", "details" => $checkData]);
        exit;
    }

    logDebug("Registrando paciente: $firstName " . ($middleName ? $middleName : '[Sin segundo nombre]') . " $lastName, Sexo: $gender, Documento: $document, DOB: $birthDate");

    // Construir el array "given" dinámicamente
    $given = [$firstName];
    if (!empty($middleName)) {
        $given[] = $middleName;
    }

    $patientData = [
        "resourceType" => "Patient",
        "name" => [
            [
                "use" => "official",
                "family" => $lastName,
                "given" => $given
            ]
        ],
        "gender" => $gender,
        "birthDate" => $birthDate,
        "identifier" => [
            [
                "use" => "official",
                "type" => [
                    "coding" => [
                        [
                            "system" => "http://terminology.hl7.org/CodeSystem/v2-0203",
                            "code" => "PT"
                        ]
                    ]
                ],
                "system" => "http://terminology.hl7.org/CodeSystem/v2-0203",
                "value" => $document
            ]
        ]
    ];

    $url = "https://openemr-domain/apis/default/fhir/Patient";
    $headers = [
        "Authorization: Bearer $accessToken",
        "Content-Type: application/json",
        "Accept: application/json"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($patientData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    if ($response === false) {
        logDebug("Error en cURL: " . curl_error($ch));
        echo json_encode(["error" => "Error al registrar paciente."]);
        exit;
    }

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 201) {
        logDebug("Error al registrar paciente. HTTP Code: $http_code. Respuesta: $response");
        echo json_encode(["error" => "Error al registrar paciente.", "details" => $response]);
        exit;
    }

    logDebug("Paciente registrado exitosamente.");
    echo json_encode(["message" => "Paciente registrado exitosamente."]);
    exit;
}

logDebug("Solicitud no válida. Método: $method, Acción: $action");
echo json_encode(["error" => "Acción no válida."]);
?>