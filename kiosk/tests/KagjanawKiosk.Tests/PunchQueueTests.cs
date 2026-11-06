using KagjanawKiosk.Sync;

namespace KagjanawKiosk.Tests;

public class PunchQueueTests : IDisposable
{
    private static readonly TimeSpan Manila = TimeSpan.FromHours(8);
    private readonly TemporaryFolder _folder = new();

    public void Dispose() => _folder.Dispose();

    [Fact]
    public void Keeps_punches_in_order_across_restarts_until_removed()
    {
        var database = _folder.File("queue.db");
        var queue = new PunchQueue(database);
        var first = queue.Enqueue("1024", new DateTimeOffset(2026, 11, 6, 7, 58, 12, 345, Manila));
        queue.Enqueue("2048", new DateTimeOffset(2026, 11, 6, 7, 59, 0, Manila));

        var reopened = new PunchQueue(database);
        var waiting = reopened.Peek(10);

        Assert.Equal(["1024", "2048"], waiting.Select(punch => punch.BiometricUserId));
        Assert.Equal(new DateTimeOffset(2026, 11, 6, 7, 58, 12, Manila), waiting[0].LoggedAt);
        Assert.Equal(first.Id, waiting[0].Id);

        reopened.Remove([waiting[0]]);

        Assert.Equal(1, reopened.Count());
        Assert.Equal("2048", reopened.Peek(10).Single().BiometricUserId);
    }

    [Fact]
    public void Moves_refused_punches_aside()
    {
        var queue = new PunchQueue(_folder.File("queue.db"));
        var punch = queue.Enqueue("1024", new DateTimeOffset(2026, 11, 6, 8, 0, 0, Manila));

        queue.Reject([punch], "The punches.0.logged_at field must be a valid date.", DateTimeOffset.Now);

        Assert.Equal(0, queue.Count());
        Assert.Equal(1, queue.RejectedCount());
    }

    [Fact]
    public void Peeks_at_most_the_requested_number()
    {
        var queue = new PunchQueue(_folder.File("queue.db"));

        for (var i = 0; i < 5; i++)
        {
            queue.Enqueue("1024", new DateTimeOffset(2026, 11, 6, 8, i, 0, Manila));
        }

        Assert.Equal(3, queue.Peek(3).Count);
    }
}
