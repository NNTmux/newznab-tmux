{{-- Preview / sample thumbnails, shown next to the cover; click opens the full image (image modal). --}}
@php
    $hasPreviewImage = isset($release->haspreview) && $release->haspreview == 1;
    $hasSampleImage = isset($release->jpgstatus) && $release->jpgstatus == 1;
    $previewImageUrl = $hasPreviewImage
        ? getImageAssetUrl('preview', $release->guid . '_thumb', asset('assets/images/no-cover.png'))
        : null;
    $sampleImageUrl = $hasSampleImage
        ? getImageAssetUrl('sample', $release->guid . '_thumb', asset('assets/images/no-cover.png'))
        : null;
    $galleryImages = array_values(array_filter([
        $hasPreviewImage ? ['url' => $previewImageUrl, 'label' => 'Preview'] : null,
        $hasSampleImage ? ['url' => $sampleImageUrl, 'label' => 'Sample'] : null,
    ]));
@endphp

@if($galleryImages !== [])
    <section class="detail-gallery mt-4" aria-label="Preview and sample images">
        <div class="flex flex-wrap gap-3">
            @foreach($galleryImages as $image)
                <figure class="m-0">
                    <button type="button"
                            class="image-modal-trigger block overflow-hidden rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                            data-image-url="{{ $image['url'] }}"
                            data-image-title="{{ $image['label'] }} Image"
                            aria-label="Open {{ strtolower($image['label']) }} image">
                        <img src="{{ $image['url'] }}"
                             alt="{{ $image['label'] }}"
                             class="detail-gallery-image h-24 w-auto max-w-56 object-cover transition hover:opacity-90 sm:h-28"
                             loading="lazy"
                             decoding="async">
                    </button>
                    <figcaption class="mt-1 text-xs text-gray-600 dark:text-gray-400">{{ $image['label'] }}</figcaption>
                </figure>
            @endforeach
        </div>
    </section>
@endif
