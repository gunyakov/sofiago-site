// Drag & drop preview for the add/edit listing photo upload. Plain JS on purpose (like
// favorite.js) — this is DOM/File API bookkeeping (rebuilding the <input>'s FileList via
// DataTransfer), not state that benefits from Vue's reactivity. The actual upload is a normal
// multipart form submit on save, not an AJAX call — this widget only manages what the user
// sees and can remove *before* submitting.
const MAX_FILES = 8
const MAX_BYTES = 5 * 1024 * 1024
const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp']

function mountGallery(root) {
    const input = root.querySelector('input[type="file"]')
    const preview = root.querySelector('.gallery-preview')
    const dropzone = root.querySelector('.gallery-dropzone')
    const hint = root.querySelector('.gallery-hint')

    if (!input || !preview || !dropzone) {
        return
    }

    // Server-rendered translations (Sofiago\Core\Lang) — see listing-form.tpl.php's
    // data-remove-label/data-photos-suffix on .gallery-upload.
    const removeLabel = root.dataset.removeLabel || 'Remove'
    const photosSuffix = root.dataset.photosSuffix || 'photos'

    let files = []

    function syncInput() {
        const dt = new DataTransfer()
        files.forEach((file) => dt.items.add(file))
        input.files = dt.files
    }

    function render() {
        preview.innerHTML = ''
        files.forEach((file, index) => {
            const url = URL.createObjectURL(file)
            const wrap = document.createElement('div')
            wrap.className = 'gallery-thumb position-relative rounded-3 overflow-hidden'
            wrap.innerHTML =
                '<img src="' + url + '" alt="">' +
                '<button type="button" class="gallery-remove" aria-label="' + removeLabel + '">&times;</button>'
            wrap.querySelector('.gallery-remove').addEventListener('click', function () {
                files.splice(index, 1)
                syncInput()
                render()
            })
            preview.appendChild(wrap)
        })
        if (hint) {
            hint.textContent = files.length + ' / ' + MAX_FILES + ' ' + photosSuffix
        }
    }

    function addFiles(newFiles) {
        for (const file of newFiles) {
            if (files.length >= MAX_FILES) break
            if (!ALLOWED_TYPES.includes(file.type)) continue
            if (file.size > MAX_BYTES) continue
            files.push(file)
        }
        syncInput()
        render()
    }

    input.addEventListener('change', function () {
        addFiles(Array.from(input.files))
    })

    dropzone.addEventListener('click', function () {
        input.click()
    })

    ;['dragover', 'dragenter'].forEach(function (evt) {
        dropzone.addEventListener(evt, function (e) {
            e.preventDefault()
            dropzone.classList.add('dragover')
        })
    })

    ;['dragleave', 'drop'].forEach(function (evt) {
        dropzone.addEventListener(evt, function (e) {
            e.preventDefault()
            dropzone.classList.remove('dragover')
        })
    })

    dropzone.addEventListener('drop', function (e) {
        if (e.dataTransfer) {
            addFiles(Array.from(e.dataTransfer.files))
        }
    })

    render()
}

function init() {
    document.querySelectorAll('.gallery-upload').forEach(mountGallery)
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init)
} else {
    init()
}
