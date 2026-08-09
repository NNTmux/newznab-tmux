            <!-- Sample/Preview Images -->
            @php
                $hasPreviewImage = isset($release->haspreview) && $release->haspreview == 1;
                $hasSampleImage = isset($release->jpgstatus) && $release->jpgstatus == 1;
                $previewImageUrl = $hasPreviewImage
                    ? getImageAssetUrl('preview', $release->guid . '_thumb', asset('assets/images/no-cover.png'))
                    : null;
                $sampleImageUrl = $hasSampleImage
                    ? getImageAssetUrl('sample', $release->guid . '_thumb', asset('assets/images/no-cover.png'))
                    : null;
            @endphp

            @if($hasPreviewImage || $hasSampleImage)
                <div class="border-b border-gray-200 dark:border-gray-700 pb-4">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-3 flex items-center">
                        <i class="fas fa-images mr-2 text-primary-600"></i>
                        @if($hasPreviewImage && $hasSampleImage)
                            Preview & Sample Images
                        @elseif($hasPreviewImage)
                            Preview Image
                        @else
                            Sample Image
                        @endif
                    </h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        @if($hasPreviewImage)
                            <!-- Preview image -->
                            <div>
                                <div class="block cursor-pointer image-modal-trigger" data-image-url="{{ $previewImageUrl }}" data-image-title="Preview Image">
                                    <img src="{{ $previewImageUrl }}"
                                         alt="Preview"
                                         class="detail-gallery-image w-full h-auto rounded-lg"
                                         loading="lazy">
                                </div>
                                <p class="text-xs text-gray-500 mt-1 text-center">Preview</p>
                            </div>
                        @endif

                        @if($hasSampleImage)
                            <!-- Sample image -->
                            <div>
                                <div class="block cursor-pointer image-modal-trigger" data-image-url="{{ $sampleImageUrl }}" data-image-title="Sample Image">
                                    <img src="{{ $sampleImageUrl }}"
                                         alt="Sample"
                                         class="detail-gallery-image w-full h-auto rounded-lg"
                                         loading="lazy">
                                </div>
                                <p class="text-xs text-gray-500 mt-1 text-center">Sample</p>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
