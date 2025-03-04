<?php

namespace Awcodes\Curator\Components\Modals;

use Awcodes\Curator\Models\Curation;
use Awcodes\Curator\Models\Media;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Facades\Image;
use Livewire\Component;

class CuratorCuration extends Component
{
    public string $statePath;

    public string $modalId;

    public Media $media;

    public array|null $presets;

    public function saveCuration($data = null): void
    {
//        dd($data);
        $image = Image::make(Storage::disk($this->media->disk)->path($this->media->path));

        ray($data);

        $aspectWidth = floor(($data['canvasData']['width'] / $data['canvasData']['naturalWidth']) * $data['width']);
        $aspectHeight = floor(($data['canvasData']['height'] / $data['canvasData']['naturalHeight']) * $data['height']);

        $image->orientate();

        if ($image->exif('Orientation') > 1) {
            $rotateCorrection = match ($image->exif('Orientation')) {
                3, 4 => 180,
                5, 6 => 90,
                7, 8 => 270,
                default => 0
            };

            $image->rotate($rotateCorrection - $data['rotate']);
        } else {
            $image->rotate($data['rotate']);
        }

        if ($data['scaleX'] === -1) {
            $image->flip('v');
        }

        if ($data['scaleY'] === -1) {
            $image->flip('h');
        }

        $image->crop($data['width'], $data['height'], $data['x'], $data['y'])
            ->resize($aspectWidth, $aspectHeight)
            ->encode($data['format'] ?? 'jpg', $data['quality'] ?? 60);

        // save image to directory base on media
        $curationPath = $this->media->directory . '/' . $this->media->name . '/' . $data['key'] . '.' . $image->extension;

        Storage::disk($this->media->disk)->put($curationPath, $image->stream());

        $curation = [
            'key' => $data['key'] ?? $aspectWidth . 'x' . $aspectHeight,
            'disk' => $this->media->disk,
            'directory' => $this->media->name,
            'name' => ($data['key'] ?? $aspectWidth . 'x' . $aspectHeight) . '.' . $image->extension,
            'path' => $curationPath,
            'width' => $aspectWidth,
            'height' => $aspectHeight,
            'size' => $image->filesize(),
            'type' => $image->mime(),
            'ext' => $image->extension,
            'url' => Storage::disk($this->media->disk)->url($curationPath),
        ];

        $this->dispatchBrowserEvent('add-curation', ['curation' => $curation, 'statePath' => $this->statePath]);
    }

    public function render(): View
    {
        return view('curator::components.modals.curator-curation');
    }
}
