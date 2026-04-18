@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3>Upload Image to MinIO</h3>
            </div>
            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('images.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    
                    <div class="mb-3">
                        <label for="image" class="form-label">Choose Image</label>
                        <input type="file" 
                               class="form-control @error('image') is-invalid @enderror" 
                               id="image" 
                               name="image" 
                               accept="image/*" 
                               required>
                        @error('image')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Supported formats: JPEG, PNG, JPG, GIF (Max: 2MB)</div>
                    </div>
                    
                    <div id="preview" class="mb-3" style="display: none;">
                        <label>Preview:</label>
                        <img id="image-preview" src="#" alt="Preview" style="max-width: 100%; max-height: 300px;">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Upload to MinIO</button>
                    <a href="{{ route('images.index') }}" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('image').onchange = function(evt) {
        const preview = document.getElementById('preview');
        const imagePreview = document.getElementById('image-preview');
        
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                imagePreview.src = e.target.result;
                preview.style.display = 'block';
            }
            reader.readAsDataURL(this.files[0]);
        }
    }
</script>
@endsection