@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between mb-3">
            <h2>Image Gallery</h2>
            <a href="{{ route('images.create') }}" class="btn btn-primary">Upload New Image</a>
        </div>

        @if($images->count() > 0)
            <div class="row">
                @foreach($images as $image)
                    <div class="col-md-3 mb-4">
                        <div class="card">
                            <img src="{{ url('/image/'.$image->path) }}" 
                                 class="card-img-top" 
                                 alt="{{ $image->original_name }}"
                                 style="height: 200px; object-fit: cover;">
                            <div class="card-body">
                                <h6 class="card-title">{{ Str::limit($image->original_name, 20) }}</h6>
                                <p class="card-text small">
                                    Size: {{ number_format($image->size / 1024, 2) }} KB<br>
                                    Type: {{ $image->mime_type }}
                                </p>
                                <div class="btn-group" role="group">
                                    <a href="{{ route('images.show', $image->id) }}" 
                                       class="btn btn-sm btn-info">View</a>
                                    <form action="{{ route('images.destroy', $image->id) }}" 
                                          method="POST" 
                                          onsubmit="return confirm('Delete this image?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="alert alert-info">No images uploaded yet.</div>
        @endif
    </div>
</div>
@endsection