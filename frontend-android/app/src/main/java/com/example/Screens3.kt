package com.example

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.Send
import androidx.compose.material.icons.filled.AutoAwesome
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.ui.theme.CyanAccent
import com.example.ui.theme.PinkAccent
import com.example.ui.theme.PurpleAccent

data class ChatMessage(val id: String, val sender: String, val text: String, val isBot: Boolean)

@Composable
fun ChatScreen(viewModel: NovaViewModel) {
    val messages = remember { mutableStateListOf(
        ChatMessage("1", "Aulasync IA", "¡Hola! Soy Aulasync, tu asistente educativo. ¿En qué puedo ayudarte hoy revisando las notas o planificando actividades?", true)
    )}
    var currentInput by remember { mutableStateOf("") }

    Column(modifier = Modifier.fillMaxSize().background(MaterialTheme.colorScheme.background)) {
        Box(
            modifier = Modifier.fillMaxWidth().background(Brush.horizontalGradient(listOf(PurpleAccent, CyanAccent))).padding(16.dp),
            contentAlignment = Alignment.Center
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(Icons.Filled.AutoAwesome, contentDescription = "IA", tint = Color.White)
                Spacer(modifier = Modifier.width(8.dp))
                Text("Aulasync Assistant", color = Color.White, fontWeight = FontWeight.Bold, fontSize = 20.sp)
            }
        }
        
        LazyColumn(modifier = Modifier.weight(1f).padding(16.dp), reverseLayout = true) {
            items(messages.reversed()) { msg ->
                ChatBubble(msg)
                Spacer(modifier = Modifier.height(12.dp))
            }
        }

        Row(
            modifier = Modifier.fillMaxWidth().background(MaterialTheme.colorScheme.surface).padding(12.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            OutlinedTextField(
                value = currentInput,
                onValueChange = { currentInput = it },
                modifier = Modifier.weight(1f),
                placeholder = { Text("Escribe tu mensaje...") },
                shape = RoundedCornerShape(24.dp),
                colors = OutlinedTextFieldDefaults.colors(
                    focusedBorderColor = CyanAccent,
                    unfocusedBorderColor = MaterialTheme.colorScheme.surfaceVariant
                )
            )
            Spacer(modifier = Modifier.width(8.dp))
            IconButton(
                onClick = {
                    if (currentInput.isNotBlank()) {
                        messages.add(ChatMessage(System.currentTimeMillis().toString(), "User", currentInput, false))
                        val cmd = currentInput
                        currentInput = ""
                        // Mock bot response
                        messages.add(ChatMessage((System.currentTimeMillis()+1).toString(), "Aulasync IA", "Procesando el contexto de tus alumnos para responder a: '$cmd'...", true))
                    }
                },
                modifier = Modifier.background(PurpleAccent, CircleShape)
            ) {
                Icon(Icons.AutoMirrored.Filled.Send, contentDescription = "Enviar", tint = Color.White)
            }
        }
    }
}

@Composable
fun ChatBubble(message: ChatMessage) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = if (message.isBot) Arrangement.Start else Arrangement.End
    ) {
        if (message.isBot) {
            Box(
                modifier = Modifier
                    .fillMaxWidth(0.8f)
                    .clip(RoundedCornerShape(16.dp, 16.dp, 16.dp, 4.dp))
                    .background(MaterialTheme.colorScheme.surfaceVariant)
                    .border(1.dp, Brush.horizontalGradient(listOf(PurpleAccent, CyanAccent)), RoundedCornerShape(16.dp, 16.dp, 16.dp, 4.dp))
                    .padding(16.dp)
            ) {
                Text(message.text, color = MaterialTheme.colorScheme.onSurface)
            }
        } else {
            Box(
                modifier = Modifier
                    .fillMaxWidth(0.8f)
                    .clip(RoundedCornerShape(16.dp, 16.dp, 4.dp, 16.dp))
                    .background(CyanAccent.copy(alpha = 0.2f))
                    .padding(16.dp)
            ) {
                Text(message.text, color = MaterialTheme.colorScheme.onSurface)
            }
        }
    }
}
