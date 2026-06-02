document.addEventListener('DOMContentLoaded', function() {
    const slider = document.querySelector('.slides');
    
    if (slider) {
        const slides = document.querySelectorAll('.slide');
        const dotsContainer = document.querySelector('.slider-dots');
        let current = 0;
        
        if (dotsContainer) {
            slides.forEach(function(_, i) {
                const dot = document.createElement('span');
                dot.className = 'dot' + (i === 0 ? ' active' : '');
                dot.onclick = function() {
                    goTo(i);
                };
                dotsContainer.appendChild(dot);
            });
        }
        
        function goTo(i) {
            current = i;
            slider.style.transform = 'translateX(-' + (i * 100) + '%)';
            if (dotsContainer) {
                document.querySelectorAll('.dot').forEach(function(d, j) {
                    d.classList.toggle('active', j === i);
                });
            }
        }
        
        const nextBtn = document.querySelector('.slider-btn.next');
        const prevBtn = document.querySelector('.slider-btn.prev');
        
        if (nextBtn) {
            nextBtn.onclick = function() {
                goTo((current + 1) % slides.length);
            };
        }
        
        if (prevBtn) {
            prevBtn.onclick = function() {
                goTo((current - 1 + slides.length) % slides.length);
            };
        }
        
        setInterval(function() {
            goTo((current + 1) % slides.length);
        }, 4000);
    }
    
    const isCustom = document.getElementById('isCustom');
    if (isCustom) {
        isCustom.onchange = function() {
            const serviceSelect = document.getElementById('serviceSelect');
            const customService = document.getElementById('customService');
            if (serviceSelect && customService) {
                serviceSelect.style.display = this.checked ? 'none' : 'block';
                customService.style.display = this.checked ? 'block' : 'none';
                if (!this.checked) {
                    customService.value = '';
                }
            }
        };
    }
    
    window.review = function(orderId) {
        document.getElementById('reviewOrderId').value = orderId;
        document.getElementById('reviewModal').style.display = 'flex';
    };
    
    const modal = document.getElementById('reviewModal');
    if (modal) {
        modal.onclick = function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        };
    }
    
    const reviewForm = document.getElementById('reviewForm');
    if (reviewForm) {
        reviewForm.onsubmit = async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'add_review');
            
            try {
                const response = await fetch('index.php?action=add_review', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const data = await response.json();
                
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Ошибка');
                }
            } catch (err) {
                alert('Ошибка отправки');
            }
        };
    }
});