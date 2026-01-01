<?php
// /HRIS/includes/test_endpoint.php
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Test endpoint is working!',
    'timestamp' => date('Y-m-d H:i:s')
]);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>

<body>
    <script>
        // Temporary test function
        function testEndpoint() {
            fetch('/HRIS/includes/test_endpoint.php')
                .then(response => response.json())
                .then(data => {
                    console.log('Test response:', data);
                    showAlert(`Test successful: ${data.message}`, 'success');
                })
                .catch(error => {
                    console.error('Test error:', error);
                    showAlert(`Test failed: ${error.message}`, 'error');
                });
        }

        // Call this in your console to test
    </script>
</body>

</html>