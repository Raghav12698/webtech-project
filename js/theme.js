// Function to toggle between light and dark themes
function toggleTheme() {
    const body = document.body;
    const currentTheme = body.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    
    body.setAttribute('data-theme', newTheme);
    
    // Save theme preference
    localStorage.setItem('theme', newTheme);
    
    // Update toggle button position
    const toggleThumb = document.querySelector('.theme-toggle .toggle-thumb');
    if (toggleThumb) {
        toggleThumb.style.transform = newTheme === 'dark' ? 'translateX(30px)' : 'translateX(0)';
    }
}

// Function to initialize theme based on saved preference
function initializeTheme() {
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.body.setAttribute('data-theme', savedTheme);
    
    // Update toggle button position
    const toggleThumb = document.querySelector('.theme-toggle .toggle-thumb');
    if (toggleThumb && savedTheme === 'dark') {
        toggleThumb.style.transform = 'translateX(30px)';
    }
}

// Initialize theme when page loads
document.addEventListener('DOMContentLoaded', initializeTheme); 