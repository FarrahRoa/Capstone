@php
    $rows = $rows ?? [];
@endphp
<table class="data details-pdf">
    <thead>
        <tr>
            <th>ID</th>
            <th>Requester</th>
            <th>Email</th>
            <th>Role</th>
            <th>Affiliation</th>
            <th>Space</th>
            <th>Schedule</th>
            <th>Status &amp; workflow</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            @php
                $id = $row['reservation_number'] ?? $row['reservation_id'] ?? '';
                $aff = trim((string) ($row['requester_affiliation'] ?? ''));
                $co = trim((string) ($row['requester_college_office'] ?? ''));
                $affDisplay = $aff !== '' ? $aff : ($co !== '' ? $co : '—');

                $schedule = '—';
                if (! empty($row['start_at']) && ! empty($row['end_at'])) {
                    try {
                        $s = \Illuminate\Support\Carbon::parse($row['start_at']);
                        $e = \Illuminate\Support\Carbon::parse($row['end_at']);
                        if ($s->toDateString() === $e->toDateString()) {
                            $schedule = \App\Support\ReservationDisplayFormat::date($s)."\n".$s->format('g:i A').' – '.$e->format('g:i A');
                        } else {
                            $schedule = \App\Support\ReservationDisplayFormat::dateAndTimes($s, $e);
                        }
                    } catch (\Throwable $ex) {
                        $schedule = ($row['start_at'] ?? '')."\n→ ".($row['end_at'] ?? '');
                    }
                }

                $wf = [];
                $wf[] = 'Status: '.($row['status_label'] ?? $row['status'] ?? '—');
                if (! empty($row['created_at'])) {
                    try {
                        $wf[] = 'Created: '.\App\Support\ReservationDisplayFormat::dateTimeLine($row['created_at']);
                    } catch (\Throwable $ex) {
                        $wf[] = 'Created: '.$row['created_at'];
                    }
                }
                if (! empty($row['verified_at'])) {
                    try {
                        $wf[] = 'Verified: '.\App\Support\ReservationDisplayFormat::dateTimeLine($row['verified_at']);
                    } catch (\Throwable $ex) {
                        $wf[] = 'Verified: '.$row['verified_at'];
                    }
                }
                if (! empty($row['approved_at'])) {
                    try {
                        $wf[] = 'Approved: '.\App\Support\ReservationDisplayFormat::dateTimeLine($row['approved_at']);
                    } catch (\Throwable $ex) {
                        $wf[] = 'Approved: '.$row['approved_at'];
                    }
                }
                if (! empty($row['approved_by'])) {
                    $wf[] = 'Approver: '.$row['approved_by'];
                }
                if (! empty($row['rejected_reason'])) {
                    $wf[] = 'Rejection: '.$row['rejected_reason'];
                }
                $workflow = implode("\n", $wf);
            @endphp
            <tr class="details-pdf-row">
                <td class="details-id">{{ $id }}</td>
                <td>{{ $row['requester_name'] ?? '—' }}</td>
                <td class="details-wrap">{{ $row['requester_email'] ?? '—' }}</td>
                <td>{{ $row['requester_role'] ?? '—' }}</td>
                <td class="details-wrap">{{ $affDisplay }}</td>
                <td class="details-wrap">{{ $row['space_name'] ?? '—' }}</td>
                <td class="details-sched">{!! nl2br(e($schedule)) !!}</td>
                <td class="details-workflow">{!! nl2br(e($workflow)) !!}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="details-empty">No reservations in this period.</td></tr>
        @endforelse
    </tbody>
</table>
