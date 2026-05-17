<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Productimage;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Childcategory;
use App\Models\Brand;
use Toastr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ZipArchive;

class InhouseProductController extends Controller
{
    private const IMPORT_HEADERS = [
        'product_code',
        'name',
        'slug',
        'category_name',
        'subcategory_name',
        'childcategory_name',
        'brand_name',
        'product_type',
        'description',
        'short_description',
        'new_price',
        'old_price',
        'purchase_price',
        'reseller_price',
        'stock',
        'status',
        'free_delivery',
        'topsale',
        'feature_product',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'primary_image_filename',
        'primary_image_path',
        'gallery_filenames',
    ];

    function __construct()
    {
        $this->middleware('permission:product-list|product-create|product-edit|product-delete', ['only' => ['index','show']]);
    }

    /**
     * Display all inhouse products (products without vendor_id)
     */
    public function index(Request $request)
    {
        // Show only inhouse products (vendor_id is null)
        $query = Product::whereNull('vendor_id')
            ->orderBy('id','DESC')
            ->with('image','category');

        if ($request->keyword) {
            $keyword = trim((string) $request->keyword);
            $query->where(function ($subQuery) use ($keyword) {
                $subQuery->where('name', 'LIKE', '%' . $keyword . '%')
                    ->orWhere('product_code', 'LIKE', '%' . $keyword . '%');
            });
        }

        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->subcategory_id) {
            $query->where('subcategory_id', $request->subcategory_id);
        }

        if ($request->childcategory_id) {
            $query->where('childcategory_id', $request->childcategory_id);
        }

        if (in_array((string) $request->status, ['0', '1'], true)) {
            $query->where('status', (int) $request->status);
        }

        $data = $query->paginate(20)->appends($request->query());
        $categories = Category::where('parent_id', 0)->where('status', 1)->select('id', 'name')->get();
        $subcategories = collect();
        $childcategories = collect();

        if ($request->category_id) {
            $subcategories = Subcategory::where('category_id', $request->category_id)
                ->select('id', 'subcategoryName')
                ->orderBy('subcategoryName')
                ->get();
        }

        if ($request->subcategory_id) {
            $childcategories = Childcategory::where('subcategory_id', $request->subcategory_id)
                ->select('id', 'childcategoryName')
                ->orderBy('childcategoryName')
                ->get();
        }
        
        return view('backEnd.inhouse_product.index', compact('data', 'categories', 'subcategories', 'childcategories'));
    }

    public function export(Request $request)
    {
        $products = Product::whereNull('vendor_id')
            ->with(['category:id,name', 'subcategory:id,subcategoryName', 'childcategory:id,childcategoryName', 'brand:id,name', 'images:id,product_id,image'])
            ->orderBy('id', 'desc')
            ->get();

        $rows = $products->map(function ($product) {
            $imagePaths = $product->images
                ->pluck('image')
                ->filter()
                ->values();

            $primaryImagePath = (string) ($imagePaths->first() ?? '');
            $primaryImageFilename = $primaryImagePath !== '' ? basename($primaryImagePath) : '';
            $galleryFilenames = $imagePaths
                ->map(fn ($path) => basename((string) $path))
                ->implode('|');

            return [
                'product_code' => (string) ($product->product_code ?? ''),
                'name' => (string) ($product->name ?? ''),
                'slug' => (string) ($product->slug ?? ''),
                'category_name' => (string) optional($product->category)->name,
                'subcategory_name' => (string) optional($product->subcategory)->subcategoryName,
                'childcategory_name' => (string) optional($product->childcategory)->childcategoryName,
                'brand_name' => (string) optional($product->brand)->name,
                'product_type' => !empty($product->is_digital) ? 'digital' : 'physical',
                'description' => (string) ($product->description ?? ''),
                'short_description' => (string) ($product->short_description ?? ''),
                'new_price' => (string) ($product->new_price ?? 0),
                'old_price' => (string) ($product->old_price ?? 0),
                'purchase_price' => (string) ($product->purchase_price ?? 0),
                'reseller_price' => (string) ($product->reseller_price ?? 0),
                'stock' => (string) ($product->stock ?? 0),
                'status' => (int) ($product->status ?? 0) === 1 ? 'Active' : 'Inactive',
                'free_delivery' => !empty($product->free_delivery) ? '1' : '0',
                'topsale' => !empty($product->topsale) ? '1' : '0',
                'feature_product' => !empty($product->feature_product) ? '1' : '0',
                'meta_title' => (string) ($product->meta_title ?? ''),
                'meta_description' => (string) ($product->meta_description ?? ''),
                'meta_keywords' => (string) ($product->meta_keywords ?? ''),
                'primary_image_filename' => $primaryImageFilename,
                'primary_image_path' => $primaryImagePath,
                'gallery_filenames' => $galleryFilenames,
            ];
        });

        $filename = 'inhouse-products-export-' . now()->format('Y-m-d-His') . '.xls';
        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->streamDownload(function () use ($rows) {
            echo "\xEF\xBB\xBF";
            echo '<table border="1"><thead><tr>';

            foreach (self::IMPORT_HEADERS as $header) {
                echo '<th>' . e($header) . '</th>';
            }

            echo '</tr></thead><tbody>';

            foreach ($rows as $row) {
                echo '<tr>';

                foreach (self::IMPORT_HEADERS as $header) {
                    echo '<td>' . e((string) ($row[$header] ?? '')) . '</td>';
                }

                echo '</tr>';
            }

            echo '</tbody></table>';
        }, $filename, $headers);
    }

    public function exportBasicCsv(Request $request)
    {
        $products = Product::whereNull('vendor_id')
            ->orderBy('id', 'desc')
            ->get(['name', 'new_price', 'product_code', 'description']);

        $filename = 'inhouse-products-basic-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($products) {
            $handle = fopen('php://output', 'w');

            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, ['Product Name', 'Price', 'Product Code', 'Product Description']);

            foreach ($products as $product) {
                fputcsv($handle, [
                    (string) ($product->name ?? ''),
                    (string) ($product->new_price ?? ''),
                    (string) ($product->product_code ?? ''),
                    trim(strip_tags((string) ($product->description ?? ''))),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'product_sheet' => 'required|file|mimes:xls,csv,txt,html,htm|max:10240',
            'product_images_zip' => 'nullable|file|mimes:zip|max:51200',
        ]);

        $rows = $this->parseSpreadsheetRows($request->file('product_sheet'));
        if (empty($rows)) {
            return redirect()->back()->withErrors(['product_sheet' => 'No product rows found in the uploaded sheet.']);
        }

        $zipMap = $this->buildImportedZipFileMap($request->file('product_images_zip'));
        $nextProductNumber = ((int) Product::max('id')) + 1;
        $created = 0;
        $updated = 0;
        $warnings = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;

            if ($this->isImportRowEmpty($row)) {
                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            $description = trim((string) ($row['description'] ?? ''));
            $categoryName = trim((string) ($row['category_name'] ?? ''));

            if ($name === '' || $description === '' || $categoryName === '') {
                $warnings[] = "Row {$line}: name, description, and category_name are required.";
                continue;
            }

            $category = Category::where('name', $categoryName)->first();
            if (!$category) {
                $warnings[] = "Row {$line}: category '{$categoryName}' not found.";
                continue;
            }

            $subcategory = null;
            $subcategoryName = trim((string) ($row['subcategory_name'] ?? ''));
            if ($subcategoryName !== '') {
                $subcategory = Subcategory::where('subcategoryName', $subcategoryName)
                    ->where('category_id', $category->id)
                    ->first();

                if (!$subcategory) {
                    $warnings[] = "Row {$line}: subcategory '{$subcategoryName}' not found under '{$categoryName}'.";
                    continue;
                }
            }

            $childcategory = null;
            $childcategoryName = trim((string) ($row['childcategory_name'] ?? ''));
            if ($childcategoryName !== '') {
                $childcategoryQuery = Childcategory::where('childcategoryName', $childcategoryName);

                if ($subcategory) {
                    $childcategoryQuery->where('subcategory_id', $subcategory->id);
                }

                $childcategory = $childcategoryQuery->first();
                if (!$childcategory) {
                    $warnings[] = "Row {$line}: childcategory '{$childcategoryName}' not found.";
                    continue;
                }
            }

            $brand = null;
            $brandName = trim((string) ($row['brand_name'] ?? ''));
            if ($brandName !== '') {
                $brand = Brand::where('name', $brandName)->first();
            }

            $product = $this->findExistingImportedProduct($row);
            $isNew = !$product;

            if (!$product) {
                $product = new Product();
                $product->vendor_id = null;
                $product->product_code = $this->generateImportProductCode($nextProductNumber);
                $nextProductNumber++;
            }

            $slugSeed = trim((string) ($row['slug'] ?? '')) !== '' ? (string) $row['slug'] : $name;
            $slug = $this->makeUniqueProductSlug($slugSeed, $product->id);

            $productType = strtolower(trim((string) ($row['product_type'] ?? 'physical')));
            $isDigital = $productType === 'digital';

            $product->name = $name;
            $product->slug = $slug;
            $product->category_id = $category->id;
            $product->subcategory_id = $subcategory?->id;
            $product->childcategory_id = $childcategory?->id;
            $product->brand_id = $brand?->id;
            $product->description = $description;
            $product->short_description = trim((string) ($row['short_description'] ?? ''));
            $product->new_price = $this->toDecimal($row['new_price'] ?? 0);
            $product->old_price = $this->toDecimal($row['old_price'] ?? 0);
            $product->purchase_price = $this->toDecimal($row['purchase_price'] ?? 0);
            $product->reseller_price = $this->toDecimal($row['reseller_price'] ?? 0);
            $product->stock = $this->toInteger($row['stock'] ?? 0);
            $product->status = $this->toBooleanFlag($row['status'] ?? 1);
            $product->free_delivery = $this->toBooleanFlag($row['free_delivery'] ?? 0);
            $product->topsale = $this->toBooleanFlag($row['topsale'] ?? 0);
            $product->feature_product = $this->toBooleanFlag($row['feature_product'] ?? 0);
            $product->meta_title = trim((string) ($row['meta_title'] ?? '')) ?: $name;
            $product->meta_description = trim((string) ($row['meta_description'] ?? '')) ?: Str::limit(strip_tags($description), 160);
            $product->meta_keywords = trim((string) ($row['meta_keywords'] ?? ''));
            $product->approval_status = 'approved';
            $product->is_digital = $isDigital ? 1 : 0;
            $product->digital_file = null;
            $product->download_limit = null;
            $product->download_expire_days = null;
            $product->advance_amount = 0;
            $product->is_wholesale = 0;

            $product->save();

            $storedImages = $this->importProductImagesFromRow($product, $row, $zipMap);
            if (!empty($storedImages)) {
                $product->meta_image = $storedImages[0];
                $product->save();
            }

            if ($isNew) {
                $created++;
            } else {
                $updated++;
            }
        }

        if (!empty($zipMap['cleanup_path'])) {
            File::deleteDirectory($zipMap['cleanup_path']);
        }

        $message = "Import completed. Created: {$created}, Updated: {$updated}.";
        if (!empty($warnings)) {
            $message .= ' Warnings: ' . implode(' | ', array_slice($warnings, 0, 5));
            if (count($warnings) > 5) {
                $message .= ' | ...';
            }
            Toastr::warning($message, 'Import Finished');
        } else {
            Toastr::success($message, 'Import Finished');
        }

        return redirect()->route('inhouse.products.index');
    }

    /**
     * Show single product details
     */
    public function show($id)
    {
        $product = Product::whereNull('vendor_id')
            ->with('image','images','category','subcategory','childcategory','brand','colors','sizes')
            ->findOrFail($id);
            
        return view('backEnd.inhouse_product.show', compact('product'));
    }

    private function parseSpreadsheetRows($file): array
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $rows = [];

        if (in_array($extension, ['csv', 'txt'], true)) {
            $handle = fopen($file->getRealPath(), 'r');
            if ($handle === false) {
                return [];
            }

            while (($row = fgetcsv($handle)) !== false) {
                $rows[] = $row;
            }

            fclose($handle);
        } else {
            $html = file_get_contents($file->getRealPath());
            if ($html === false) {
                return [];
            }

            libxml_use_internal_errors(true);
            $dom = new \DOMDocument();
            $dom->loadHTML($html);
            libxml_clear_errors();

            foreach ($dom->getElementsByTagName('tr') as $tr) {
                $cells = [];
                foreach ($tr->childNodes as $cell) {
                    if (in_array($cell->nodeName, ['td', 'th'], true)) {
                        $cells[] = trim(html_entity_decode($cell->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    }
                }

                if (!empty($cells)) {
                    $rows[] = $cells;
                }
            }
        }

        if (count($rows) < 2) {
            return [];
        }

        $headers = array_map([$this, 'normalizeImportHeader'], $rows[0]);
        $mappedRows = [];

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $mapped = [];

            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }

                $mapped[$header] = isset($row[$index]) ? trim((string) $row[$index]) : '';
            }

            if (!empty($mapped)) {
                $mappedRows[] = $mapped;
            }
        }

        return $mappedRows;
    }

    private function normalizeImportHeader(string $header): string
    {
        return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $header), '_'));
    }

    private function buildImportedZipFileMap($zipFile): array
    {
        if (!$zipFile) {
            return ['files' => []];
        }

        $extractPath = storage_path('app/tmp/product-import-' . Str::uuid());
        File::ensureDirectoryExists($extractPath);

        $zip = new ZipArchive();
        if ($zip->open($zipFile->getRealPath()) !== true) {
            File::deleteDirectory($extractPath);
            return ['files' => []];
        }

        $zip->extractTo($extractPath);
        $zip->close();

        $files = [];
        $allFiles = File::allFiles($extractPath);

        foreach ($allFiles as $file) {
            $files[strtolower($file->getFilename())] = $file->getPathname();
        }

        return [
            'files' => $files,
            'cleanup_path' => $extractPath,
        ];
    }

    private function isImportRowEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function findExistingImportedProduct(array $row): ?Product
    {
        $productCode = trim((string) ($row['product_code'] ?? ''));
        if ($productCode !== '') {
            $product = Product::whereNull('vendor_id')->where('product_code', $productCode)->first();
            if ($product) {
                return $product;
            }
        }

        $slug = trim((string) ($row['slug'] ?? ''));
        if ($slug !== '') {
            return Product::whereNull('vendor_id')->where('slug', $slug)->first();
        }

        return null;
    }

    private function generateImportProductCode(int $sequence): string
    {
        return 'P' . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function makeUniqueProductSlug(string $value, $ignoreId = null): string
    {
        $baseSlug = Str::slug($value);
        if ($baseSlug === '') {
            $baseSlug = 'product';
        }

        $slug = $baseSlug;
        $counter = 2;

        while (
            Product::where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function toDecimal($value): float
    {
        return (float) preg_replace('/[^0-9.\-]/', '', (string) $value);
    }

    private function toInteger($value): int
    {
        return (int) preg_replace('/[^0-9\-]/', '', (string) $value);
    }

    private function toBooleanFlag($value): int
    {
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'active', 'enabled'], true) ? 1 : 0;
    }

    private function importProductImagesFromRow(Product $product, array $row, array $zipMap): array
    {
        $filenames = [];
        $primaryFilename = trim((string) ($row['primary_image_filename'] ?? ''));
        $gallery = trim((string) ($row['gallery_filenames'] ?? ''));

        if ($gallery !== '') {
            $filenames = array_merge($filenames, array_filter(array_map('trim', explode('|', $gallery))));
        }

        if ($primaryFilename !== '' && !in_array($primaryFilename, $filenames, true)) {
            array_unshift($filenames, $primaryFilename);
        }

        $filenames = array_values(array_unique(array_filter($filenames)));

        if (empty($filenames) || empty($zipMap['files'])) {
            return [];
        }

        if ($product->images()->exists()) {
            foreach ($product->images as $existingImage) {
                if ($existingImage->image && File::exists(public_path($existingImage->image))) {
                    File::delete(public_path($existingImage->image));
                } elseif ($existingImage->image && File::exists($existingImage->image)) {
                    File::delete($existingImage->image);
                }
            }

            $product->images()->delete();
        }

        $storedPaths = [];
        $destinationDir = public_path('uploads/product');
        File::ensureDirectoryExists($destinationDir);

        foreach ($filenames as $filename) {
            $lookupKey = strtolower($filename);
            $sourcePath = $zipMap['files'][$lookupKey] ?? null;

            if (!$sourcePath || !File::exists($sourcePath)) {
                continue;
            }

            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $safeBase = Str::slug(pathinfo($filename, PATHINFO_FILENAME));
            $safeBase = $safeBase !== '' ? $safeBase : 'product-image';
            $targetName = now()->format('YmdHis') . '-' . Str::random(6) . '-' . $safeBase . ($extension ? '.' . strtolower($extension) : '');
            $targetPath = $destinationDir . DIRECTORY_SEPARATOR . $targetName;

            File::copy($sourcePath, $targetPath);

            $relativePath = 'public/uploads/product/' . $targetName;
            Productimage::create([
                'product_id' => $product->id,
                'image' => $relativePath,
            ]);

            $storedPaths[] = $relativePath;
        }

        return $storedPaths;
    }
}
