// js/dashboard.js: Enhanced Dashboard JS with Chart and Error Handling
document.addEventListener('DOMContentLoaded', function() {
    fetch('php/api/dashboard.php')
        .then(response => {
            if (!response.ok) throw new Error('Network error');
            return response.json();
        })
        .then(data => {
            if (data.error) {
                alert(data.error);
                return;
            }
            if (data.errors && data.errors.length) {
                console.warn('API errors:', data.errors);
                alert('알림: ' + data.errors.join(' | '));
            }
            document.getElementById('upbitBalance').textContent = Number(data.upbitBalance || 0).toLocaleString() + ' KRW';
            document.getElementById('binanceBalance').textContent = Number(data.binanceBalance || 0).toLocaleString() + ' USDT';
            document.getElementById('cumulativeProfit').textContent = Number(data.cumulativeProfit || 0).toLocaleString() + ' KRW';

            const trades = Array.isArray(data.trades) ? data.trades : [];
            const tradeHistory = document.getElementById('tradeHistory');
            tradeHistory.innerHTML = '';
            if (trades.length === 0) {
                tradeHistory.innerHTML = '<tr><td colspan="4" class="text-center">오늘 매매 내역이 없습니다.</td></tr>';
            } else {
                trades.forEach(trade => {
                    const row = `<tr>
                        <td>${trade.time}</td>
                        <td>${trade.type}</td>
                        <td>${Number(trade.amount).toLocaleString()}</td>
                        <td class="${Number(trade.profit) > 0 ? 'text-success' : 'text-danger'}">${Number(trade.profit).toLocaleString()}</td>
                    </tr>`;
                    tradeHistory.innerHTML += row;
                });
            }

            const ctx = document.getElementById('profitChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: trades.map(t => (t.time || '').split(' ')[1] || t.time || ''),
                    datasets: [{
                        label: '수익 추이',
                        data: trades.map(t => Number(t.profit)),
                        borderColor: 'rgba(75, 192, 192, 1)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.1
                    }]
                },
                options: {
                    scales: { y: { beginAtZero: true } },
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        })
        .catch(error => {
            console.error('Error:', error);
            alert('데이터 로딩 중 오류가 발생했습니다.');
        });
});
