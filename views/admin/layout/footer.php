
            </div>
        </main>
    </div>
    <script src="<?= BASE_URL ?>/js/admin/core/app.js"></script>
    <script src="https://unpkg.com/feather-icons"></script>
    <script>
      feather.replace({ class: 'feather-icon' });
    </script>
    
    <!-- Mobile Table Label Generator -->
    <script>
      (function() {
        function addTableLabels() {
          // Get all tables
          const tables = document.querySelectorAll('.data-table, .admin-table, table');
          
          tables.forEach(table => {
            // Get header cells
            const headers = Array.from(table.querySelectorAll('thead th, thead td'));
            const headerTexts = headers.map(h => h.textContent.trim());
            
            // Add data-label to body cells
            const rows = table.querySelectorAll('tbody tr, tbody > tr');
            rows.forEach(row => {
              const cells = row.querySelectorAll('td');
              cells.forEach((cell, index) => {
                if (headerTexts[index] && !cell.getAttribute('data-label')) {
                  cell.setAttribute('data-label', headerTexts[index]);
                }
              });
            });
          });
        }
        
        // Run on page load
        document.addEventListener('DOMContentLoaded', addTableLabels);
        // Re-run if content is dynamically added
        const observer = new MutationObserver(addTableLabels);
        observer.observe(document.body, { childList: true, subtree: true });
      })();
    </script>
</body>
</html>