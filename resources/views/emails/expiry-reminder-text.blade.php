Service expiry reminder
@if($isTest)
This is a test message; the service below is an example.
@endif

Your {{ $serviceType }} {{ $serviceName }} is due in {{ $daysBefore }} {{ $daysBefore === 1 ? 'day' : 'days' }}.
Due date: {{ $dueDate }}
Price: {{ $price }} {{ $currency }}

Review the service in My Idlers.
