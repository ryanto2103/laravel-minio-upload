<h1>Test No Layout</h1>
<form method="POST" action="/images" enctype="multipart/form-data">
    @csrf
    <input type="file" name="image">
    <button type="submit">Upload</button>
</form>