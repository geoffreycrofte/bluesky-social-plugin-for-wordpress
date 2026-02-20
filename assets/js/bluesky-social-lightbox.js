(function(){
    class Lightbox {
        constructor(galleryClass) {
            this.galleryClass = galleryClass;
            this.images = document.querySelectorAll(`.${galleryClass}`);
            this.currentIndex = 0;
            this.lightbox = this.createLightbox();
            this.bindEvents();
        }

        createLightbox() {
            const lightbox = document.createElement('dialog');
            lightbox.id = 'bluesky-lightbox';
            lightbox.innerHTML = `
                <div class="bluesky-lightbox-content">
                    <button type="button" class="bluesky-lightbox-close">&times;</button>
                    <div class="bluesky-lightbox-gallery-container">
                        <figure>
                            <img class="bluesky-lightbox-image" src="" alt="">
                            <figcaption class="bluesky-lightbox-image-caption"></figcaption>
                        </figure>
                    </div>
                    <button type="button" class="bluesky-lightbox-prev">&#10094;</button>
                    <button type="button" class="bluesky-lightbox-next">&#10095;</button>
                </div>
            `;
            document.body.appendChild(lightbox);
            return lightbox;
        }

        bindEvents() {
            this.images.forEach((image, index) => {
                console.log(image);
                // Skip lightbox for GIFs or elements marked as no-lightbox
                if (image.classList.contains('is-gif') || image.dataset.noLightbox === 'true') {
                    return;
                }
                image.addEventListener('click', (e) => this.openLightbox(e, index));
            });

            this.lightbox.querySelector('.bluesky-lightbox-close').addEventListener('click', () => this.closeLightbox());
            this.lightbox.querySelector('.bluesky-lightbox-prev').addEventListener('click', () => this.prevImage());
            this.lightbox.querySelector('.bluesky-lightbox-next').addEventListener('click', () => this.nextImage());

            document.addEventListener('keydown', (e) => this.handleKeydown(e));
        }

        openLightbox(e, index) {
            e.preventDefault();
            this.currentIndex = index;
            this.updateLightboxImage();
            this.lightbox.showModal();
        }

        closeLightbox() {
            this.lightbox.close();
        }

        prevImage() {
            this.currentIndex = (this.currentIndex - 1 + this.images.length) % this.images.length;
            this.updateLightboxImage();
        }

        nextImage() {
            this.currentIndex = (this.currentIndex + 1) % this.images.length;
            this.updateLightboxImage();
        }

        updateLightboxImage() {
            const image = this.images[this.currentIndex];
            this.lightbox.querySelector('.bluesky-lightbox-image').src = image.href;
            this.lightbox.querySelector('.bluesky-lightbox-image-caption').innerHTML = image.querySelector('img').alt;
        }

        handleKeydown(e) {
            switch (e.key) {
                case 'Escape':
                    this.closeLightbox();
                    break;
                case 'ArrowLeft':
                    this.prevImage();
                    break;
                case 'ArrowRight':
                    this.nextImage();
                    break;
            }
        }
    }

    // Usage
    document.addEventListener('DOMContentLoaded', () => {
        console.log('BlueSky Social Integration - Lightbox JS is working');
        new Lightbox('bluesky-gallery-image');
    });
})();