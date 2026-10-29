<script>
    const savedTheme = localStorage.getItem('dk-theme-v2');
    const initialTheme = savedTheme === 'light' ? 'light' : 'dark';
    document.documentElement.dataset.theme = initialTheme;
    const themeColor = document.querySelector('meta[name="theme-color"]');
    if (themeColor) themeColor.content = initialTheme === 'dark' ? '#0b1120' : '#f6f8fb';
</script>
