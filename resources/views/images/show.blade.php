@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3>Image Details</h3>
            </div>
            <div class="card-body">
                <div class="text-center mb-4">
                   <img src="{{ $imageUrl }}"
                         alt="{{ $image->original_name }}" 
                         class="img-fluid rounded"
                         style="max-height: 500px;">
                </div>
                
                <table class="table">
                    <tr>
                        <th>Original Name:</th>
                        <td>{{ $image->original_name }}</td>
                    </tr>
                    <tr>
                        <th>Filename:</th>
                        <td>{{ $image->filename }}</td>
                    </tr>
                    <tr>
                        <th>MIME Type:</th>
                        <td>{{ $image->mime_type }}</td>
                    </tr>
                    <tr>
                        <th>Size:</th>
                        <td>{{ number_format($image->size / 1024, 2) }} KB</td>
                    </tr>
                    <tr>
                        <th>Uploaded At:</th>
                        <td>{{ $image->created_at->format('d/m/Y H:i:s') }}</td>
                    </tr>
                    <tr>
                        <th>Image URL:</th>
                        <td><a href="{{ $imageUrl }}" target="_blank">{{ $imageUrl }}</a></td>
                    </tr>
                </table>
                
               <div class="btn-group">
               <a href="{{ $imageUrl }}" class="btn btn-success" target="_blank">Open Image</a>
               <a href="{{ route('images.index') }}" class="btn btn-secondary">Back to Gallery</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection