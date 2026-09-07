<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Chart</title>
</head>
<body>
<div>
<canvas id="myChart"></canvas>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
const ctx = document.getElementById('myChart');

new Chart(ctx, {
    type: 'bar',
    data: {
    labels: @json($labels),
    datasets: [{
        label: 'Jumlah Tamu',
        data: @json($data),
        borderWidth: 1
    }]
    },
    options: {
    scales: {
        y: {
            beginAtZero: true,
        }
    }
    }
});
</script>
</body>
</html>