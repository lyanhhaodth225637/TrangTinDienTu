
        // Toggle Like
        function toggleLike(button) {
            button.classList.toggle('liked');
            const icon = button.querySelector('i');
            if (button.classList.contains('liked')) {
                icon.classList.remove('bi-hand-thumbs-up');
                icon.classList.add('bi-hand-thumbs-up-fill');
            } else {
                icon.classList.remove('bi-hand-thumbs-up-fill');
                icon.classList.add('bi-hand-thumbs-up');
            }
        }

        // Toggle Comments Section
        function toggleComments(button) {
            const postCard = button.closest('.post-card');
            const commentsSection = postCard.querySelector('.comments-section');
            
            if (commentsSection.style.display === 'none') {
                commentsSection.style.display = 'block';
            } else {
                commentsSection.style.display = 'none';
            }
        }

        // Category filter (demo)
        document.querySelectorAll('.category-item').forEach(item => {
            item.addEventListener('click', function() {
                document.querySelectorAll('.category-item').forEach(i => i.classList.remove('active'));
                this.classList.add('active');
            });
        });
