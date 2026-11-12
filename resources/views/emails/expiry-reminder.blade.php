<!doctype html>
<html lang="en">
<body>
    <h2>Service expiry reminder</h2>
    @if($isTest)<p>This is a test message; the service below is an example.</p>@endif
    <p>Your {{ $serviceType }} <strong>{{ $serviceName }}</strong> is due in {{ $daysBefore }} {{ $daysBefore === 1 ? 'day' : 'days' }}.</p>
    <p>Due date: <strong>{{ $dueDate }}</strong><br>Price: {{ $price }} {{ $currency }}</p>
    <p>Review the service in My Idlers.</p>
</body>
</html>
