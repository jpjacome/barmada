use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateCategoriesNameEditoridUniqueIndex extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['name']); // Remove old unique index
            $table->unique(['name', 'editor_id']); // Add composite unique index
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['name', 'editor_id']);
            $table->unique('name');
        });
    }
}
