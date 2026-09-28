<?php
declare(strict_types=1);

namespace Moves\Services\Support;

use InvalidArgumentException;
use Moves\Core\Connection;
use Moves\Services\Talk\TalkTenantContext;
use PDO;
use RuntimeException;
use Throwable;

final class TicketService
{
    private int $tenantId;

    public function __construct(?int $tenantId = null)
    {
        $this->tenantId = $tenantId ?? (new TalkTenantContext())->currentTenantId();
        if ($this->tenantId <= 0) {
            throw new RuntimeException('Tenant inválido para o Support.');
        }
    }

    /** @return array<string,mixed> */
    public function create(int $actorId, array $input): array
    {
        $subject = mb_substr(trim(strip_tags((string)($input['subject'] ?? ''))), 0, 190);
        $description = trim(strip_tags((string)($input['description'] ?? '')));
        $priority = (string)($input['priority'] ?? 'normal');
        $requesterId = $this->nullableId($input['requester_user_id'] ?? $actorId);
        $assignedId = $this->nullableId($input['assigned_user_id'] ?? null);

        if ($actorId <= 0 || $subject === '' || $description === '') {
            throw new InvalidArgumentException('Assunto, descrição e usuário responsável pela criação são obrigatórios.');
        }
        if (!in_array($priority, ['low','normal','high','urgent'], true)) {
            throw new InvalidArgumentException('Prioridade inválida.');
        }

        $pdo = Connection::getInstance();
        $this->assertMember($pdo, $actorId);
        if ($requesterId !== null) {
            $this->assertMember($pdo, $requesterId);
        }
        if ($assignedId !== null) {
            $this->assertMember($pdo, $assignedId);
        }

        try {
            $pdo->beginTransaction();
            $protocol = $this->nextProtocol($pdo);
            $stmt = $pdo->prepare('INSERT INTO support_tickets(tenant_id,requester_user_id,assigned_user_id,created_by,protocol,subject,description,status,priority,condominium_id,unit_id,due_at) VALUES(:tenant,:requester,:assigned,:creator,:protocol,:subject,:description,\'open\',:priority,:condominium,:unit,:due)');
            $stmt->execute([
                'tenant'=>$this->tenantId,
                'requester'=>$requesterId,
                'assigned'=>$assignedId,
                'creator'=>$actorId,
                'protocol'=>$protocol,
                'subject'=>$subject,
                'description'=>$description,
                'priority'=>$priority,
                'condominium'=>$this->nullableId($input['condominium_id'] ?? null),
                'unit'=>$this->nullableId($input['unit_id'] ?? null),
                'due'=>$this->nullableDate($input['due_at'] ?? null),
            ]);
            $id=(int)$pdo->lastInsertId();
            $event=$pdo->prepare('INSERT INTO support_ticket_events(tenant_id,ticket_id,user_id,event_type,payload) VALUES(:tenant,:ticket,:user,\'created\',:payload)');
            $event->execute(['tenant'=>$this->tenantId,'ticket'=>$id,'user'=>$actorId,'payload'=>json_encode(['priority'=>$priority], JSON_THROW_ON_ERROR)]);
            $pdo->commit();
            return $this->findForUser($id,$actorId,true) ?? throw new RuntimeException('Chamado criado, mas não foi possível recarregá-lo.');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @return array<string,mixed>|null */
    public function findForUser(int $ticketId, int $userId, bool $allowTenantMember = false): ?array
    {
        if ($ticketId <= 0 || $userId <= 0) {
            return null;
        }
        $pdo=Connection::getInstance();
        $this->assertMember($pdo,$userId);
        $sql='SELECT * FROM support_tickets WHERE id=:id AND tenant_id=:tenant';
        if (!$allowTenantMember) {
            $sql.=' AND (requester_user_id=:user OR assigned_user_id=:user OR created_by=:user)';
        }
        $stmt=$pdo->prepare($sql.' LIMIT 1');
        $params=['id'=>$ticketId,'tenant'=>$this->tenantId];
        if (!$allowTenantMember) {
            $params['user']=$userId;
        }
        $stmt->execute($params);
        $ticket=$stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($ticket)?$ticket:null;
    }

    private function assertMember(PDO $pdo, int $userId): void
    {
        $stmt=$pdo->prepare("SELECT 1 FROM talk_tenant_users WHERE tenant_id=:tenant AND user_id=:user AND status='active' LIMIT 1");
        $stmt->execute(['tenant'=>$this->tenantId,'user'=>$userId]);
        if (!$stmt->fetchColumn()) {
            throw new RuntimeException('Usuário sem acesso ao tenant do Support.');
        }
    }

    private function nextProtocol(PDO $pdo): string
    {
        $prefix='SUP-'.date('Ymd').'-';
        $stmt=$pdo->prepare('SELECT protocol FROM support_tickets WHERE tenant_id=:tenant AND protocol LIKE :prefix ORDER BY id DESC LIMIT 1 FOR UPDATE');
        $stmt->execute(['tenant'=>$this->tenantId,'prefix'=>$prefix.'%']);
        $last=(string)($stmt->fetchColumn() ?: '');
        $sequence=$last!=='' ? ((int)substr($last,-6))+1 : 1;
        return $prefix.str_pad((string)$sequence,6,'0',STR_PAD_LEFT);
    }

    private function nullableId(mixed $value): ?int
    {
        $id=(int)($value ?? 0);
        return $id>0?$id:null;
    }

    private function nullableDate(mixed $value): ?string
    {
        $value=trim((string)($value ?? ''));
        if ($value==='') {
            return null;
        }
        $date=\DateTimeImmutable::createFromFormat('Y-m-d H:i:s',$value);
        if (!$date || $date->format('Y-m-d H:i:s')!==$value) {
            throw new InvalidArgumentException('Prazo inválido.');
        }
        return $value;
    }
}
