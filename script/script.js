document.addEventListener('DOMContentLoaded', function() {
    const formControls = document.querySelectorAll('.form-control');
    formControls.forEach(control => {
        if (!control.nextElementSibling?.classList.contains('floating-label')) {
            const label = control.previousElementSibling;
            if (label && label.tagName === 'LABEL') {
                const floatingLabel = document.createElement('span');
                floatingLabel.className = 'floating-label';
                floatingLabel.textContent = label.textContent;
                control.parentNode.insertBefore(floatingLabel, control.nextSibling);
                label.style.display = 'none';
            }
        }

        control.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });

        control.addEventListener('blur', function() {
            if (!this.value) {
                this.parentElement.classList.remove('focused');
            }
        });

        if (control.value) {
            control.parentElement.classList.add('focused');
        }
    });

    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.classList.add('loading');
                submitBtn.disabled = true;
                
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<span class="loading"></span> Отправка...';
                
                setTimeout(() => {
                    submitBtn.classList.remove('loading');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                    
                    this.classList.add('success');
                    setTimeout(() => this.classList.remove('success'), 2000);
                }, 2000);
            }
        });
    });

    const cards = document.querySelectorAll('.event-card, .report-item');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });

    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    const loginContainer = document.querySelector('.login-container');
    if (loginContainer) {
        document.addEventListener('mousemove', (e) => {
            const x = (e.clientX / window.innerWidth - 0.5) * 20;
            const y = (e.clientY / window.innerHeight - 0.5) * 20;
            loginContainer.style.transform = `perspective(1000px) rotateY(${x}deg) rotateX(${y}deg)`;
        });
    }

    const titles = document.querySelectorAll('.section-title, .login-title');
    titles.forEach(title => {
        const text = title.textContent;
        title.textContent = '';
        let i = 0;
        
        function typeWriter() {
            if (i < text.length) {
                title.textContent += text.charAt(i);
                i++;
                setTimeout(typeWriter, 50);
            }
        }
        
        setTimeout(typeWriter, 500);
    });
});

function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    notification.textContent = message;
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.zIndex = '10000';
    notification.style.maxWidth = '300px';
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

function initTooltips() {
    const elements = document.querySelectorAll('[data-tooltip]');
    elements.forEach(el => {
        el.addEventListener('mouseenter', showTooltip);
        el.addEventListener('mouseleave', hideTooltip);
    });
}

function showTooltip(e) {
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = this.getAttribute('data-tooltip');
    tooltip.style.cssText = `
        position: absolute;
        background: #2d3748;
        color: white;
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 0.875rem;
        white-space: nowrap;
        z-index: 1000;
        pointer-events: none;
    `;
    
    document.body.appendChild(tooltip);
    
    const rect = this.getBoundingClientRect();
    tooltip.style.left = rect.left + 'px';
    tooltip.style.top = (rect.top - tooltip.offsetHeight - 8) + 'px';
    
    this._tooltip = tooltip;
}

function hideTooltip() {
    if (this._tooltip) {
        this._tooltip.remove();
        this._tooltip = null;
    }
}