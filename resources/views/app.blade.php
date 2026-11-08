<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#6d4fc2">
    <title>Flohmarkt</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='7' fill='%236d4fc2'/%3E%3Cpath d='M9 13h14l-1.5 11h-11z' fill='white'/%3E%3Cpath d='M12 13a4 4 0 0 1 8 0' stroke='white' stroke-width='2' fill='none'/%3E%3C/svg%3E">
    <script>try{var t=localStorage.getItem('bc-theme');if(t&&t!=='system')document.documentElement.dataset.theme=t}catch(e){}</script>
    @vite(['resources/scss/site.scss', 'resources/js/main.js'])
</head>
<body>
    <a class="skip-link" href="#main">Zum Inhalt springen</a>
    <div id="app"></div>
</body>
</html>
