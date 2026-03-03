<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; color: #333; }
        .container { padding: 20px; border: 1px solid #eee; border-radius: 10px; }
        .status { font-weight: bold; color: #2563eb; text-transform: uppercase; }
    </style>
</head>
<body>
    <div class="container">
        <h2>¡Hola, {{ $order->user->name }}!</h2>
        <p>Tu pedido con ticket <strong>{{ $order->ticket }}</strong> ha cambiado de estado.</p>
        <p>Estado actual: <span class="status">{{ $statusLabel }}</span></p>
        <p>Gracias por confiar en MakerLab.</p>
    </div>
</body>
</html>