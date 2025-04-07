// Form validation functions
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return true;

    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    let isValid = true;

    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.classList.add('error');
            isValid = false;
        } else {
            input.classList.remove('error');
        }
    });

    return isValid;
}

// Password confirmation validation
function validatePassword() {
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    
    if (password && confirmPassword && password.value !== confirmPassword.value) {
        alert('Passwords do not match!');
        return false;
    }
    return true;
}

// Table sorting functionality
function sortTable(tableId, columnIndex) {
    const table = document.getElementById(tableId);
    if (!table) return;

    const rows = Array.from(table.rows).slice(1); // Skip header row
    const isNumeric = !isNaN(rows[0].cells[columnIndex].textContent.trim());

    rows.sort((a, b) => {
        const aValue = a.cells[columnIndex].textContent.trim();
        const bValue = b.cells[columnIndex].textContent.trim();

        if (isNumeric) {
            return parseFloat(aValue) - parseFloat(bValue);
        }
        return aValue.localeCompare(bValue);
    });

    // Remove existing rows
    rows.forEach(row => table.deleteRow(row.rowIndex));

    // Add sorted rows
    rows.forEach(row => table.appendChild(row));
}

// Search functionality
function searchTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    if (!input || !table) return;

    const filter = input.value.toLowerCase();
    const rows = table.getElementsByTagName('tr');

    for (let i = 1; i < rows.length; i++) { // Start from 1 to skip header
        const cells = rows[i].getElementsByTagName('td');
        let found = false;

        for (let j = 0; j < cells.length; j++) {
            const cell = cells[j];
            if (cell.textContent.toLowerCase().indexOf(filter) > -1) {
                found = true;
                break;
            }
        }

        rows[i].style.display = found ? '' : 'none';
    }
}

// Simple chart drawing using canvas
function drawBarChart(canvasId, data, labels, colors) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    const width = canvas.width;
    const height = canvas.height;
    const barWidth = width / data.length / 1.5;
    const maxValue = Math.max(...data);

    ctx.clearRect(0, 0, width, height);

    // Draw bars
    data.forEach((value, i) => {
        const barHeight = (value / maxValue) * (height - 40);
        const x = (width / data.length) * i + (width / data.length - barWidth) / 2;
        const y = height - barHeight - 20;

        ctx.fillStyle = colors[i % colors.length];
        ctx.fillRect(x, y, barWidth, barHeight);

        // Draw label
        ctx.fillStyle = '#000';
        ctx.font = '12px Arial';
        ctx.textAlign = 'center';
        ctx.fillText(labels[i], x + barWidth/2, height - 5);
        ctx.fillText(value, x + barWidth/2, y - 5);
    });
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(form.id)) {
                e.preventDefault();
            }
            if (form.id === 'registrationForm' && !validatePassword()) {
                e.preventDefault();
            }
        });
    });

    // Search functionality
    const searchInputs = document.querySelectorAll('.table-search');
    searchInputs.forEach(input => {
        input.addEventListener('keyup', function() {
            searchTable(input.id, input.dataset.tableId);
        });
    });
}); 