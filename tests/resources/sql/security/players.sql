-- player 1 and 2 belong to anna, 3 to bernd, 4 to the admin
INSERT INTO player (player_id, playlist_id, UID, uuid, player_name)
VALUES
(1, 0, 2, '11111111-1111-4111-8111-111111111111', 'Annas Player 1'),
(2, 0, 2, '22222222-2222-4222-8222-222222222222', 'Annas Player 2'),
(3, 0, 3, '33333333-3333-4333-8333-333333333333', 'Bernds Player'),
(4, 0, 1, '44444444-4444-4444-8444-444444444444', 'Admin Player');
