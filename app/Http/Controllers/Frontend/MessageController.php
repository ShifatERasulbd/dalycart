
<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Message;

class MessageController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'sender' => 'required|in:user,bot',
            'content' => 'required|string',
        ]);

        $message = Message::create($validated);

        return response()->json(['status' => 'success', 'message' => $message]);
    }
}
?>
