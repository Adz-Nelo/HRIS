<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found | HRMS</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/HRMS.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary-red: #991b1b;
            --slate-800: #1e293b;
            --slate-500: #64748b;
            --bg-gray: #f8fafc;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-gray);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--slate-800);
        }

        .error-container {
            text-align: center;
            max-width: 500px;
            padding: 40px;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .error-code {
            font-size: 120px;
            font-weight: 900;
            color: var(--primary-red);
            line-height: 1;
            margin-bottom: 10px;
            letter-spacing: -5px;
        }

        .error-icon {
            font-size: 50px;
            color: var(--slate-800);
            margin-bottom: 20px;
        }

        h1 {
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 15px;
            text-transform: uppercase;
        }

        p {
            color: var(--slate-500);
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .button-group {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background-color: var(--slate-800);
            color: #fff;
        }

        .btn-primary:hover {
            background-color: #0f172a;
            transform: translateY(-2px);
        }

        .btn-outline {
            border: 1px solid #e2e8f0;
            color: var(--slate-500);
        }

        .btn-outline:hover {
            background-color: #f1f5f9;
            color: var(--slate-800);
        }

        .security-note {
            margin-top: 30px;
            font-size: 11px;
            color: #94a3b8;
            border-top: 1px solid #f1f5f9;
            padding-top: 20px;
        }
    </style>
</head>
<body>

    <div class="error-container">
        <div class="error-icon">
            <i class="fa-solid fa-shield-slash"></i>
        </div>
        <div class="error-code">404</div>
        <h1>Lost in the System?</h1>
        <p>The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.</p>
        
        <div class="button-group">
            <a href="javascript:history.back()" class="btn btn-outline">
                <i class="fa-solid fa-arrow-left"></i> Go Back
            </a>
            <a href="javascript:history.back()" class="btn btn-primary">
                <i class="fa-solid fa-house"></i> Dashboard
            </a>
        </div>

        <div class="security-note">
            <i class="fa-solid fa-circle-info"></i> 
            Logged Event ID: <span style="font-family: monospace;"><?= bin2hex(random_bytes(4)) ?></span> | 
            IP: <?= htmlspecialchars($_SERVER['REMOTE_ADDR']) ?>
        </div>
    </div>

</body>
</html>